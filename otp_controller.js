const inputs = document.querySelectorAll('#otp-container input');
inputs.forEach((input, index) => {
    input.addEventListener('input', () => {
        if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) inputs[index - 1].focus();
    });
});

async function verifyOTP() {
    const code = Array.from(inputs).map(i => i.value).join('');
    if (code.length < 6) return alert("Please enter the full 6-digit code.");

    const response = await fetch('otp_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'verify', otp: code })
    });

    const result = await response.json();

    if (result.success) {
        window.location.href = result.redirect; 
    } else {
        alert(result.message);
    }
}

async function resendOTP() {
    const response = await fetch('otp_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'resend' })
    });
    const result = await response.json();
    alert(result.message);
}