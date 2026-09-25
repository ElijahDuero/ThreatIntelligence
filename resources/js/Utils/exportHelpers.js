/**
 * Downloads a Blob or text content as a file in the browser.
 *
 * @param {Blob|string} content
 * @param {string} filename
 * @param {string} mimeType
 */
export function downloadBlob(content, filename, mimeType = 'text/plain') {
    const blob = content instanceof Blob ? content : new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
}

/**
 * Downloads data as a formatted JSON file.
 *
 * @param {any} data
 * @param {string} filename
 */
export function downloadJson(data, filename) {
    const jsonStr = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
    downloadBlob(jsonStr, filename, 'application/json');
}

/**
 * Downloads formatted Markdown text.
 *
 * @param {string} markdownText
 * @param {string} filename
 */
export function downloadMarkdown(markdownText, filename) {
    downloadBlob(markdownText, filename, 'text/markdown;charset=utf-8');
}
