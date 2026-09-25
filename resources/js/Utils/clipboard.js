import { ref } from 'vue';

/**
 * Copy text to clipboard with legacy fallback.
 *
 * @param {string} text
 * @returns {Promise<boolean>}
 */
export async function copyToClipboard(text) {
    if (!text) return false;
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }

        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        const success = document.execCommand('copy');
        document.body.removeChild(textArea);
        return success;
    } catch (err) {
        console.error('Failed to copy text to clipboard:', err);
        return false;
    }
}

/**
 * Composable for tracking copied states across multiple elements.
 *
 * @param {number} timeoutMs
 * @returns {{ copiedId: import('vue').Ref<any>, copy: (text: string, id?: any) => Promise<boolean>, isCopied: (id?: any) => boolean }}
 */
export function useClipboard(timeoutMs = 2000) {
    const copiedId = ref(null);

    const copy = async (text, id = 'default') => {
        const success = await copyToClipboard(text);
        if (success) {
            copiedId.value = id;
            setTimeout(() => {
                if (copiedId.value === id) {
                    copiedId.value = null;
                }
            }, timeoutMs);
        }
        return success;
    };

    const isCopied = (id = 'default') => copiedId.value === id;

    return {
        copiedId,
        copy,
        isCopied,
    };
}
