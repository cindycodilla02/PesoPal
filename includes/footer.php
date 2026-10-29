</main>
<footer class="site-footer">Student Expense and Allowance Management System</footer>
<script>
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = button.parentElement.querySelector('input');
        const showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(showing));
        button.innerHTML = showing
            ? '<svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0112 5c6.4 0 10 7 10 7a15 15 0 01-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.3 10.3 0 004-.8"/></svg>'
            : '<svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    });
});
</script>
</body>
</html>
