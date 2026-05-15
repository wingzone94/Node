/** @typedef {'A'|'B'|'C'|'D'|'E'} HypothesisId */

/**
 * Debug session logging (browser → Cursor debug ingest).
 * @param {string} location
 * @param {string} message
 * @param {Record<string, unknown>} data
 * @param {HypothesisId} hypothesisId
 */
export function debugLog(location, message, data, hypothesisId) {
    // #region agent log
    fetch('http://127.0.0.1:7293/ingest/59945043-a847-4077-80ce-7eb3b10f7c0f', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '03fb7d' },
        body: JSON.stringify({
            sessionId: '03fb7d',
            location,
            message,
            data,
            hypothesisId,
            timestamp: Date.now(),
            runId: data.runId || 'pre-fix',
        }),
    }).catch(() => {});
    // #endregion
}
