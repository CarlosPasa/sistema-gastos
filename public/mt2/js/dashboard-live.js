document.addEventListener('DOMContentLoaded', () => {
  const year = document.getElementById('dashboard-year');
  const month = document.getElementById('dashboard-month');
  const period = document.getElementById('dashboard-period');
  const form = period?.closest('form');
  if (!year || !month || !period || !form) return;
  const refresh = () => { period.value = `${year.value}-${month.value}`; };
  year.addEventListener('change', refresh);
  month.addEventListener('change', refresh);
  form.addEventListener('submit', refresh);
  refresh();
});
