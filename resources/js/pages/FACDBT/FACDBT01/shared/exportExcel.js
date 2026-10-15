/**
 * Export tabel master ke Excel memakai tombol "excel" DataTables yang disembunyikan di class DataTable
 * (pola yang sama dengan HITUAM01).
 *
 * Karena DataTable memakai server-side processing, tombol excel hanya mengekspor baris yang sedang
 * dimuat. Supaya semua data ikut, tabel dimuat sementara dengan "All" (page length -1), diekspor,
 * lalu dikembalikan ke jumlah baris semula.
 */
export function exportTableToExcel($table) {
    const table = $table.DataTable();
    const pageLength = table.page.len();

    if (pageLength === -1) {
        table.button('.buttons-excel').trigger();
        return;
    }

    table.one('draw', () => {
        table.button('.buttons-excel').trigger();
        table.page.len(pageLength).draw(false);
    });
    table.page.len(-1).draw();
}
