<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HouseholdAnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        Gate::authorize('manage-users');

        // Récupérer tous les users avec leurs infos d'adresse
        $users = User::query()
            ->select([
                'id', 'name', 'email', 'role', 
                'address_line1', 'address_line2', 'postal_code', 'city',
                'birth_latitude', 'birth_longitude',
                'phone', 'date_of_birth'
            ])
            ->orderBy('name')
            ->get();

        // Définition des foyers basée sur la segmentation fournie
        $households = [
            'Chez PapiMami' => [
                'members_names' => ['Christophe', 'Valérie'],
                'members' => [],
                'addresses' => [],
                'coords' => [],
                'notes' => []
            ],
            'Chez PépéMémé' => [
                'members_names' => ['Gilles', 'Claude Sebille'],
                'members' => [],
                'addresses' => [],
                'coords' => [],
                'notes' => []
            ],
            'Chez Manon et Mathieu' => [
                'members_names' => ['Manon Sebille', 'Mathieu Turaud', 'Noé', 'Paloma'],
                'members' => [],
                'addresses' => [],
                'coords' => [],
                'notes' => []
            ],
            'Chez Hugo et Noélie' => [
                'members_names' => ['Hugo Sebille', 'Noélie'],
                'members' => [],
                'addresses' => [],
                'coords' => [],
                'notes' => []
            ],
            'Chez Nico' => [
                'members_names' => ['Nicolas', 'Marcus', 'Charlotte Sebille'],
                'members' => [],
                'addresses' => [],
                'coords' => [],
                'notes' => []
            ],
        ];

        // Mapper les users aux foyers
        foreach ($users as $user) {
            foreach ($households as $householdName => &$household) {
                foreach ($household['members_names'] as $searchName) {
                    if (stripos($user->name, $searchName) !== false || 
                        stripos($searchName, $user->name) !== false) {
                        
                        $household['members'][] = [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $user->role,
                            'phone' => $user->phone,
                        ];

                        // Collecter l'adresse si elle existe
                        if ($user->address_line1 || $user->city) {
                            $address = [
                                'source_user_id' => $user->id,
                                'source_user_name' => $user->name,
                                'line1' => $user->address_line1,
                                'line2' => $user->address_line2,
                                'postal_code' => $user->postal_code,
                                'city' => $user->city,
                                'complete' => !empty($user->address_line1) && !empty($user->city) && !empty($user->postal_code),
                            ];
                            $household['addresses'][] = $address;
                        }

                        // Collecter les coordonnées si elles existent
                        if ($user->birth_latitude && $user->birth_longitude) {
                            $household['coords'][] = [
                                'source_user_id' => $user->id,
                                'source_user_name' => $user->name,
                                'lat' => $user->birth_latitude,
                                'lon' => $user->birth_longitude,
                                'note' => 'birth_place_coords',
                            ];
                        }

                        break;
                    }
                }
            }
        }

        // Détecter les incohérences
        $inconsistencies = [];
        foreach ($households as $householdName => $household) {
            $issues = [];

            // Vérifier si tous les membres ont été trouvés
            $foundCount = count($household['members']);
            $expectedCount = count($household['members_names']);
            if ($foundCount < $expectedCount) {
                $issues[] = "Membres manquants : {$foundCount}/{$expectedCount} trouvés";
            }

            // Vérifier les adresses multiples/contradictoires
            $uniqueAddresses = [];
            foreach ($household['addresses'] as $addr) {
                $key = trim(strtolower($addr['line1'] . '|' . $addr['city']));
                if (!isset($uniqueAddresses[$key])) {
                    $uniqueAddresses[$key] = [];
                }
                $uniqueAddresses[$key][] = $addr['source_user_name'];
            }

            if (count($uniqueAddresses) > 1) {
                $issues[] = "Adresses multiples/contradictoires : " . count($uniqueAddresses) . " adresses différentes";
            }

            if (count($household['addresses']) === 0) {
                $issues[] = "Aucune adresse trouvée pour ce foyer";
            }

            foreach ($household['addresses'] as $addr) {
                if (!$addr['complete']) {
                    $issues[] = "Adresse incomplète pour " . $addr['source_user_name'];
                }
            }

            if (count($issues) > 0) {
                $inconsistencies[$householdName] = $issues;
            }
        }

        return response()->json([
            'storage_schema' => [
                'table' => 'users',
                'address_columns' => ['address_line1', 'address_line2', 'postal_code', 'city'],
                'geo_columns' => ['birth_latitude', 'birth_longitude'],
                'notes' => 'Les coordonnées geo existantes sont liées au lieu de naissance (birth_place), pas au domicile actuel',
            ],
            'households' => $households,
            'inconsistencies' => $inconsistencies,
            'total_users' => $users->count(),
            'users_with_address' => $users->filter(fn($u) => !empty($u->address_line1) || !empty($u->city))->count(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
