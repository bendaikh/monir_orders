@props(['message' => null, 'email' => null])

<section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
    <h2 class="text-base font-semibold">Identifiants Google Sheets requis</h2>
    <p class="mt-2 text-sm leading-6">{{ $message ?: 'La connexion Google Sheets n\'est pas configurée. Aucun import ni transfert ne sera déclaré comme réussi tant que l\'écriture n\'est pas confirmée par Google.' }}</p>
    <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm leading-6">
        <li>Créez un compte de service Google avec l'accès Google Sheets.</li>
        <li>Enregistrez le fichier JSON dans <code class="rounded bg-white/70 px-1.5 py-0.5">storage/app/private/google-service-account.json</code> ou indiquez son chemin dans <code class="rounded bg-white/70 px-1.5 py-0.5">GOOGLE_SHEETS_CREDENTIALS</code>.</li>
        <li>Partagez chaque tableur source et destination avec l'adresse du compte de service.</li>
    </ol>
    @if ($email)
        <p class="mt-3 text-sm">Compte détecté : <strong>{{ $email }}</strong></p>
    @endif
</section>
