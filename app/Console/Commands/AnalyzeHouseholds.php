<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AnalyzeHouseholds extends Command
{
    protected $signature = 'households:analyze';
    protected $description = 'Analyze household data from users table and produce foyer structure';

    public function handle()
    {
        $this->info('🏠 ANALYSE DES FOYERS - Lecture de la table users...');
        $this->newLine();

        // A. Retrouver tous les users avec leurs adresses
        $users = User::query()
            ->select([
                'id', 'name', 'email', 'role',
                'address_line1', 'address_line2', 'postal_code', 'city',
                'birth_latitude', 'birth_longitude',
                'phone', 'date_of_birth'
            ])
            ->orderBy('name')
            ->get();

        $this->table(
            ['ID', 'Nom', 'Email', 'Adresse Ligne 1', 'CP', 'Ville', 'Phone'],
            $users->map(fn($u) => [
                $u->id,
                $u->name,
                $u->email,
                Str::limit($u->address_line1 ?? '—', 30),
                $u->postal_code ?? '—',
                $u->city ?? '—',
                $u->phone ?? '—',
            ])
        );

        $this->newLine(2);
        $this->info('📋 DÉFINITION DES FOYERS');
        $this->newLine();

        // B. Définir les foyers avec leurs membres attendus
        $householdsDefinition = [
            [
                'home_key' => 'chez-papimami',
                'label' => 'Chez PapiMami',
                'search_names' => ['Christophe', 'Valérie'],
                'host_priority' => ['Christophe', 'Valérie'], // ordre de préférence pour adresse canonique
            ],
            [
                'home_key' => 'chez-pepememe',
                'label' => 'Chez PépéMémé',
                'search_names' => ['Gilles', 'Claude'],
                'host_priority' => ['Gilles', 'Claude'],
            ],
            [
                'home_key' => 'chez-manon-mathieu',
                'label' => 'Chez Manon et Mathieu',
                'search_names' => ['Manon', 'Mathieu', 'Noé', 'Paloma'],
                'host_priority' => ['Manon', 'Mathieu'],
            ],
            [
                'home_key' => 'chez-hugo-noelie',
                'label' => 'Chez Hugo et Noélie',
                'search_names' => ['Hugo', 'Noélie'],
                'host_priority' => ['Hugo', 'Noélie'],
            ],
            [
                'home_key' => 'chez-nico',
                'label' => 'Chez Nico',
                'search_names' => ['Nicolas', 'Marcus', 'Charlotte'],
                'host_priority' => ['Nicolas'],
            ],
        ];

        $households = [];

        foreach ($householdsDefinition as $def) {
            $this->line("─────────────────────────────────────────");
            $this->info("🏡 {$def['label']} ({$def['home_key']})");
            $this->newLine();

            $household = [
                'home_key' => $def['home_key'],
                'label' => $def['label'],
                'host_user_id' => null,
                'host_user_name' => null,
                'member_user_ids' => [],
                'members' => [],
                'address' => null,
                'address_components' => [],
                'missing_fields' => [],
                'inconsistencies' => [],
            ];

            // Trouver les membres
            foreach ($def['search_names'] as $searchName) {
                $found = $users->filter(function($u) use ($searchName) {
                    return stripos($u->name, $searchName) !== false;
                })->first();

                if ($found) {
                    $household['member_user_ids'][] = $found->id;
                    $household['members'][] = [
                        'id' => $found->id,
                        'name' => $found->name,
                        'email' => $found->email,
                        'has_address' => !empty($found->address_line1) || !empty($found->city),
                        'address_complete' => !empty($found->address_line1) && !empty($found->postal_code) && !empty($found->city),
                    ];
                    $this->line("  ✓ Trouvé: <info>{$found->name}</info> (ID: {$found->id})");
                    
                    if ($found->address_line1 || $found->city) {
                        $this->line("    Adresse: {$found->address_line1}, {$found->postal_code} {$found->city}");
                    } else {
                        $this->line("    <comment>Pas d'adresse renseignée</comment>");
                    }
                } else {
                    $this->line("  <fg=red>✗ NON TROUVÉ: {$searchName}</>");
                    $household['missing_fields'][] = "Membre '{$searchName}' non trouvé en DB";
                }
            }

            $this->newLine();

            // Déterminer l'adresse canonique (host)
            $hostUser = null;
            foreach ($def['host_priority'] as $hostName) {
                $candidate = $users->filter(function($u) use ($hostName) {
                    return stripos($u->name, $hostName) !== false;
                })->first();

                if ($candidate && $candidate->address_line1 && $candidate->postal_code && $candidate->city) {
                    $hostUser = $candidate;
                    break;
                }
            }

            if ($hostUser) {
                $household['host_user_id'] = $hostUser->id;
                $household['host_user_name'] = $hostUser->name;
                
                $addressParts = array_filter([
                    $hostUser->address_line1,
                    $hostUser->address_line2,
                    $hostUser->postal_code,
                    $hostUser->city,
                ]);
                
                $household['address'] = implode(', ', $addressParts);
                $household['address_components'] = [
                    'line1' => $hostUser->address_line1,
                    'line2' => $hostUser->address_line2,
                    'postal_code' => $hostUser->postal_code,
                    'city' => $hostUser->city,
                ];

                $this->line("  <fg=green>📍 Adresse canonique (host: {$hostUser->name}):</>");
                $this->line("     {$household['address']}");
            } else {
                $this->line("  <fg=yellow>⚠ Aucune adresse complète trouvée pour ce foyer</>");
                $household['missing_fields'][] = 'Adresse canonique manquante';
            }

            // Vérifier les incohérences
            $addresses = [];
            foreach ($household['members'] as $member) {
                $user = $users->firstWhere('id', $member['id']);
                if ($user && ($user->address_line1 || $user->city)) {
                    $key = strtolower(trim($user->address_line1 . '|' . $user->city));
                    if (!isset($addresses[$key])) {
                        $addresses[$key] = [];
                    }
                    $addresses[$key][] = $user->name;
                }
            }

            if (count($addresses) > 1) {
                $this->newLine();
                $this->line("  <fg=yellow>⚠ Adresses différentes détectées:</>");
                foreach ($addresses as $key => $names) {
                    $this->line("     - " . implode(', ', $names));
                }
                $household['inconsistencies'][] = count($addresses) . ' adresses différentes entre les membres';
            }

            // Vérifier les champs manquants
            if ($hostUser) {
                $missing = [];
                if (empty($hostUser->address_line1)) $missing[] = 'address_line1';
                if (empty($hostUser->postal_code)) $missing[] = 'postal_code';
                if (empty($hostUser->city)) $missing[] = 'city';
                
                if (count($missing) > 0) {
                    $household['missing_fields'][] = 'Champs manquants: ' . implode(', ', $missing);
                }
            }

            $households[] = $household;
            $this->newLine();
        }

        // C. Synthèse finale
        $this->newLine(2);
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->info('📊 SYNTHÈSE FINALE DES FOYERS');
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        foreach ($households as $h) {
            $this->line("🏡 <info>{$h['label']}</info> (<comment>{$h['home_key']}</comment>)");
            $this->line("   Host: {$h['host_user_name']} (ID: {$h['host_user_id']})");
            $this->line("   Membres: " . count($h['member_user_ids']) . " users (IDs: " . implode(', ', $h['member_user_ids']) . ")");
            $this->line("   Adresse: " . ($h['address'] ?? '<fg=red>MANQUANTE</>'));
            
            if (count($h['missing_fields']) > 0) {
                $this->line("   <fg=yellow>Manques:</>");
                foreach ($h['missing_fields'] as $missing) {
                    $this->line("     - {$missing}");
                }
            }
            
            if (count($h['inconsistencies']) > 0) {
                $this->line("   <fg=yellow>Incohérences:</>");
                foreach ($h['inconsistencies'] as $inc) {
                    $this->line("     - {$inc}");
                }
            }
            
            $this->newLine();
        }

        // Export JSON
        $jsonOutput = [
            'generated_at' => now()->toIso8601String(),
            'total_users' => $users->count(),
            'households' => $households,
            'storage_info' => [
                'table' => 'users',
                'address_fields' => ['address_line1', 'address_line2', 'postal_code', 'city'],
                'geo_fields' => ['birth_latitude', 'birth_longitude'],
                'note' => 'Les coordonnées geo sont pour le lieu de naissance, pas le domicile',
            ],
        ];

        $jsonPath = storage_path('app/households_analysis.json');
        file_put_contents($jsonPath, json_encode($jsonOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->newLine();
        $this->info("✅ Analyse exportée: {$jsonPath}");
        $this->newLine();

        return 0;
    }
}
