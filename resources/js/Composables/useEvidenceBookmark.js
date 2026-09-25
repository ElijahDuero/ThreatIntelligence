import { ref } from 'vue';

/**
 * Composable for orchestrating evidence bookmarking to Case Dossiers.
 *
 * @param {Array} defaultInvestigations
 */
export function useEvidenceBookmark(defaultInvestigations = []) {
    const showBookmarkModal = ref(false);
    const bookmarkTarget = ref({
        title: '',
        url: '',
        notes: '',
        severity: 'medium',
        investigation_id: defaultInvestigations?.[0]?.id || null,
    });

    const openBookmark = (data = {}) => {
        bookmarkTarget.value = {
            title: data.title || '',
            url: data.url || data.target_url || '',
            notes: data.notes || data.summary || data.snippet || data.description || '',
            severity: data.severity || 'medium',
            investigation_id: data.investigation_id ?? (defaultInvestigations?.[0]?.id || null),
        };
        showBookmarkModal.value = true;
    };

    const closeBookmark = () => {
        showBookmarkModal.value = false;
    };

    return {
        showBookmarkModal,
        bookmarkTarget,
        openBookmark,
        closeBookmark,
    };
}
