import axios from 'axios';
import { toast } from '../../../../helpers';

/**
 * Export master data dari server (file dibuat di server, bukan dari isi DataTable).
 * - ids kosong : semua data diexport
 * - ids terisi : hanya data yang dicentang yang diexport
 * - params      : filter tambahan yang ikut dikirim (opsional), contoh periode & keyword
 */
export async function exportMasterData(url, ids, $button = $('#export-excel'), params = {}) {
    const original = $button.html();
    $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Exporting...');

    try {
        const response = await axios.post(url, { ...params, ids: Array.from(ids) }, { responseType: 'blob' });

        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
        const filename = match ? decodeURIComponent(match[1]) : 'export.csv';

        const link = document.createElement('a');
        link.href = URL.createObjectURL(response.data);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    } catch (error) {
        toast.error('Failed to export data.');
    } finally {
        $button.prop('disabled', false).html(original);
    }
}
