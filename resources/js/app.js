import './bootstrap';
import './toast-session';
/*
  Add custom scripts here
*/
$.extend($.fn.dataTable.defaults, {
  processing: false,
  dom: `
      <"row mx-3 my-0 justify-content-between"
        <"d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto" l>
        <"d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto mt-0" f>
      >
      <"table table-responsive" t>
      <"row"
        <"col-12 col-md-6"i>
        <"col-12 col-md-6"p>
      >`,
  language: {
    lengthMenu: 'Show _MENU_ entries'
  },
  initComplete: function () {
    $('div.dt-search').addClass('mt-md-6 mt-0');
  },
  ajax: {
    error: function (xhr, error, thrown) {
      if (xhr.status === 401) {
        window.location.reload();
      }
    }
  }
});

import.meta.glob([
  '../assets/img/**',
  // '../assets/json/**',
  '../assets/vendor/fonts/**'
]);
