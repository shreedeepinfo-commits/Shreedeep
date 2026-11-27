{{-- Meta Embedded Signup. Needs: $connectUrl, $extra (sent with the code), $onDone (JS function taking the JSON reply). --}}
<script>
    window.fbAsyncInit = function () {
        FB.init({ appId: @json(config('whatsapp.app_id')), autoLogAppEvents: true, xfbml: true, version: @json(config('whatsapp.graph_version')) });
    };

    // Embedded Signup posts the new WABA and phone number ids to this window.
    let signupSession = null;
    window.addEventListener('message', (event) => {
        if (!event.origin.endsWith('facebook.com')) return;
        try {
            const data = JSON.parse(event.data);
            if (data.type !== 'WA_EMBEDDED_SIGNUP') return;
            if (String(data.event).startsWith('FINISH')) {
                signupSession = data.data;
            } else if (data.event === 'CANCEL') {
                showStatus('Signup was cancelled' + (data.data?.current_step ? ' at step ' + data.data.current_step : '') + '.', true);
            } else if (data.event === 'ERROR') {
                showStatus('Meta reported an error: ' + (data.data?.error_message || 'unknown'), true);
            }
        } catch (e) { /* not JSON, ignore */ }
    });

    function showStatus(text, isError) {
        const el = document.getElementById('connect-status');
        el.textContent = text;
        el.className = 'mb-4 rounded px-4 py-3 text-sm ' + (isError ? 'bg-red-100 text-red-900' : 'bg-emerald-100 text-emerald-900');
    }

    async function finishSignup(code) {
        // The session info message can arrive just after the login callback.
        for (let i = 0; i < 20 && !signupSession; i++) await new Promise(r => setTimeout(r, 250));
        if (!signupSession?.phone_number_id || !signupSession?.waba_id) {
            showStatus('Meta did not return the WhatsApp number details. Please try again.', true);
            return;
        }
        showStatus('Connecting your number…', false);
        const res = await fetch(@json($connectUrl), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({
                ...@json((object) $extra),
                code,
                waba_id: signupSession.waba_id,
                phone_number_id: signupSession.phone_number_id,
                business_id: signupSession.business_id || null,
                coexistence: document.getElementById('coexistence').checked,
            }),
        });
        const json = await res.json();
        if (res.ok) ({!! $onDone !!})(json); else showStatus(json.message || 'Could not connect the number.', true);
    }

    document.getElementById('connect-whatsapp').addEventListener('click', () => {
        signupSession = null;
        const coexistence = document.getElementById('coexistence').checked;
        FB.login((response) => {
            if (response.authResponse?.code) finishSignup(response.authResponse.code);
            else showStatus('Signup window closed before finishing.', true);
        }, {
            config_id: @json(config('whatsapp.config_id')),
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {},
                featureType: coexistence ? 'whatsapp_business_app_onboarding' : '',
                sessionInfoVersion: '3',
            },
        });
    });
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
