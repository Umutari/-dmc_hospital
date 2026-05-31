/* DMC Hospital — Global JS */

/* sidebar toggle */
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  sb.classList.toggle('open');
  ov.classList.toggle('show');
}

/* DataTables default init */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.dmc-table').forEach(function (el) {
    /* remove colspan fallback rows — DataTables handles empty state itself */
    el.querySelectorAll('tbody tr').forEach(function (tr) {
      var tds = tr.querySelectorAll('td, th');
      if (tds.length === 1 && tds[0].hasAttribute('colspan')) { tr.remove(); }
    });
    $(el).DataTable({
      responsive: true,
      pageLength: 15,
      language: {
        search: '', searchPlaceholder: 'Search...', lengthMenu: 'Show _MENU_ entries',
        emptyTable: 'No records found', zeroRecords: 'No matching records'
      },
      dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
           "<'row'<'col-12'tr>>" +
           "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
    });
  });
});

/* AJAX helper — returns a Promise<response JSON> */
function dmcPost(url, data) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).then(r => r.json());
}

function defaultErr(j) {
  Swal.fire({ icon: 'error', title: 'Error', text: j.error || 'Something went wrong.', confirmButtonColor: '#0A2342' });
}

function confirmDelete(url, msg) {
  Swal.fire({
    title: 'Are you sure?',
    text: msg || 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#D14A30',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it!'
  }).then(r => { if (r.isConfirmed) window.location = url; });
}

function toast(msg, type = 'success') {
  Swal.fire({
    toast: true, position: 'top-end', icon: type,
    title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true
  });
}
