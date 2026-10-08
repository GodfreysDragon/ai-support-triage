/**
 * Copies text to the clipboard.
 *
 * The Clipboard API only exists in secure contexts (HTTPS or localhost), so on
 * plain-http dev sites such as http://ai-support-triage.test it falls back to
 * the older execCommand('copy'), which still works there. Throws if neither
 * route succeeds.
 */
export async function copyToClipboard(text: string): Promise<void> {
    if (window.isSecureContext && navigator.clipboard) {
        await navigator.clipboard.writeText(text);

        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        if (!document.execCommand('copy')) {
            throw new Error('The browser rejected the copy command.');
        }
    } finally {
        textarea.remove();
    }
}
