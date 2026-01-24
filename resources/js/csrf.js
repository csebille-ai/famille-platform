export function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')
    const token = meta?.getAttribute('content')
    return (token || '').trim()
}
