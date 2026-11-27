@if ($c = session('credentials'))
    <div class="mb-5 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5">
        <div class="font-semibold text-emerald-800">Login details for {{ $c['name'] }}</div>
        <p class="text-sm text-emerald-700">Share these once. The password is not shown again (they can change it after login).</p>
        <pre id="cred" class="mt-3 bg-white border border-emerald-200 rounded-xl px-4 py-3 text-sm whitespace-pre-wrap">Login: {{ $c['url'] }}
Email: {{ $c['email'] }}
Password: {{ $c['password'] }}</pre>
        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cred').textContent); this.textContent='Copied ✓'" class="mt-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Copy</button>
    </div>
@endif
