const state = {
  apiBase: localStorage.getItem('apiBase') || `${window.location.origin}/api`,
  authUser: null,
  customers: { page: 1, lastPage: 1 },
  vehicles: { page: 1, lastPage: 1 },
  services: [],
  checkinItems: [],
  selectedServiceIds: new Set(),
  editingCustomerId: null,
  editingVehicleId: null,
  _customerRows: [],
  _vehicleRows: [],
};

const themeState = {
  current: localStorage.getItem('theme') || 'dark',
};

const $ = (id) => document.getElementById(id);
const views = [...document.querySelectorAll('.view')];

function toast(message, isError = false) {
  const el = $('toast');
  el.textContent = message;
  el.style.background = isError ? '#b91c1c' : '#111827';
  el.classList.remove('hidden');
  setTimeout(() => el.classList.add('hidden'), 2600);
}

async function api(path, options = {}) {
  const res = await fetch(`${state.apiBase}${path}`, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });

  const data = await res.json().catch(() => ({}));

  if (!res.ok || data.success === false) {
    let msg = data.message || data.error || `Request failed (${res.status})`;

    if (data.errors) {
      if (typeof data.errors === 'object') {
        msg = Object.values(data.errors).flat().join(' | ');
      } else {
        msg = String(data.errors);
      }
    }

    throw new Error(msg);
  }

  return data;
}

function statusBadge(status) {
  const map = {
    PENDING: '<span class="badge badge-yellow">PENDING</span>',
    IN_PROGRESS: '<span class="badge badge-blue">IN PROGRESS</span>',
    COMPLETED: '<span class="badge badge-green">COMPLETED</span>',
    CANCELLED: '<span class="badge badge-red">CANCELLED</span>',
  };

  return map[status] || status;
}

function toRows(tableId, columns, rows, actionBuilder = null) {
  const table = $(tableId);
  const head = `<thead><tr>${columns.map((c) => `<th>${c}</th>`).join('')}${actionBuilder ? '<th>Actions</th>' : ''}</tr></thead>`;
  const body = `<tbody>${rows
    .map((row) => `<tr>${columns.map((c) => `<td>${row[c] ?? ''}</td>`).join('')}${actionBuilder ? `<td>${actionBuilder(row)}</td>` : ''}</tr>`)
    .join('')}</tbody>`;
  table.innerHTML = head + body;
}

function setView(viewId) {
  views.forEach((v) => v.classList.toggle('hidden', v.id !== viewId));
  document.querySelectorAll('.nav-link').forEach((b) => b.classList.toggle('active', b.dataset.view === viewId));
}

function setCustomerFormMode(isEdit = false) {
  $('customerSubmitBtn').textContent = isEdit ? 'Update Customer' : 'Add Customer';
  $('customerCancelBtn').classList.toggle('hidden', !isEdit);
}

function resetCustomerForm() {
  $('customerForm').reset();
  state.editingCustomerId = null;
  setCustomerFormMode(false);
}

function setVehicleFormMode(isEdit = false) {
  $('vehicleSubmitBtn').textContent = isEdit ? 'Update Vehicle' : 'Add Vehicle';
  $('vehicleCancelBtn').classList.toggle('hidden', !isEdit);
}

function resetVehicleForm() {
  $('vehicleForm').reset();
  state.editingVehicleId = null;
  setVehicleFormMode(false);
  applyVehicleCategoryRules();
}

function applyTheme(theme) {
  themeState.current = theme === 'light' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', themeState.current);
  localStorage.setItem('theme', themeState.current);
  $('themeToggleBtn').textContent = themeState.current === 'dark' ? '☀️ Light' : '🌙 Dark';
}

function bindThemeToggle() {
  applyTheme(themeState.current);
  $('themeToggleBtn').addEventListener('click', () => {
    applyTheme(themeState.current === 'dark' ? 'light' : 'dark');
  });
}

function bindNav() {
  document.querySelectorAll('.nav-link').forEach((btn) => {
    btn.addEventListener('click', () => setView(btn.dataset.view));
  });
}

function applyVehicleCategoryRules() {
  const isMotor = $('vehicleCategorySelect').value === 'MOTOR';
  $('vehicleSizeSelect').disabled = isMotor;
  $('vehicleTypeInput').disabled = isMotor;

  if (isMotor) {
    $('vehicleTypeInput').value = '';
  }
}

function applyWaiverRules() {
  const leaveVehicleChecked = $('leaveVehicleCheckbox')?.checked;
  const waiverBox = $('waiverBox');
  const waiverNameInput = $('waiverNameInput');
  const waiverAcceptedCheckbox = $('waiverAcceptedCheckbox');

  if (!waiverBox || !waiverNameInput || !waiverAcceptedCheckbox) return;

  waiverBox.classList.toggle('hidden', !leaveVehicleChecked);

  waiverNameInput.required = !!leaveVehicleChecked;
  waiverAcceptedCheckbox.required = !!leaveVehicleChecked;

  if (!leaveVehicleChecked) {
    waiverNameInput.value = '';
    waiverAcceptedCheckbox.checked = false;
  }
}

function selectedServiceRuleStatus(service, selectedGroups, vehicleCategory = 'CAR') {
  if (vehicleCategory === 'MOTOR') {
    if (['PACKAGE', 'ADDON', 'BUNDLE'].includes(service.service_group)) return { ok: false, reason: 'Not for MOTOR' };
    if (service.service_group === 'MOTOR_MAIN' && selectedGroups.has('MOTOR_MAIN')) return { ok: false, reason: 'Only one MOTOR_MAIN' };
  }
  if (vehicleCategory === 'CAR') {
    if (service.service_group === 'MOTOR_MAIN') return { ok: false, reason: 'Not for CAR' };
    if (service.service_group === 'BUNDLE' && (selectedGroups.has('PACKAGE') || selectedGroups.has('ADDON') || selectedGroups.has('BUNDLE'))) {
      return { ok: false, reason: 'Complete is exclusive' };
    }
    if ((service.service_group === 'PACKAGE' || service.service_group === 'ADDON') && selectedGroups.has('BUNDLE')) {
      return { ok: false, reason: 'Cannot mix with Complete' };
    }
    if (service.service_group === 'PACKAGE' && selectedGroups.has('PACKAGE')) return { ok: false, reason: 'Only one package' };
  }
  return { ok: true };
}

async function loadSession() {
  $('apiBaseInput').value = state.apiBase;
  try {
    const res = await api('/auth/me');
    state.authUser = res.data;
    $('sessionBadge').textContent = `Logged in: ${state.authUser.full_name || state.authUser.email}`;
    $('logoutBtn').classList.remove('hidden');
    $('loginPanel').classList.add('hidden');
    await hydrateDashboard();
  } catch {
    $('sessionBadge').textContent = 'Guest';
  }
}

async function hydrateDashboard() {
  const date = new Date().toISOString().slice(0, 10);
  const [customers, vehicles, orders, report] = await Promise.allSettled([
    api('/customers'),
    api('/vehicles'),
    api('/job-orders'),
    api(`/reports/daily?date=${date}`),
  ]);

  const kpis = [
    ['Customers', customers.value?.data?.total || 0],
    ['Vehicles', vehicles.value?.data?.total || 0],
    ['Job Orders', orders.value?.data?.total || 0],
    ['Gross Today', `₱${report.value?.data?.summary?.gross_total || 0}`],
  ];

  $('dashboardCards').innerHTML = kpis
    .map(([name, value]) => `<article class="kpi"><h3>${name}</h3><strong>${value}</strong></article>`)
    .join('');
}

async function loadCustomers() {
  const search = $('customerSearch').value.trim();
  const res = await api(`/customers?page=${state.customers.page}&search=${encodeURIComponent(search)}`);
  const payload = res.data;

  state.customers.lastPage = payload.last_page || 1;
  state._customerRows = payload.data || [];

  toRows(
    'customersTable',
    ['customer_id', 'full_name', 'contact_number', 'created_at'],
    state._customerRows,
    (row) => {
      const id = row.customer_id ?? row.id;
      return `<button class="btn btn-ghost" onclick="editCustomer(${id})">Edit</button>`;
    }
  );

  $('customersPage').textContent = `Page ${payload.current_page} / ${payload.last_page}`;
}

async function loadVehicles() {
  const search = $('vehicleSearch').value.trim();
  const res = await api(`/vehicles?page=${state.vehicles.page}&search=${encodeURIComponent(search)}`);
  const payload = res.data;

  state.vehicles.lastPage = payload.last_page || 1;
  state._vehicleRows = payload.data || [];

  toRows(
    'vehiclesTable',
    [
      'vehicle_id',
      'customer_id',
      'plate_number',
      'vehicle_category',
      'vehicle_size',
      'vehicle_type'
    ],
    state._vehicleRows,
    (row) => {
      const id = row.vehicle_id ?? row.id;
      return `<button class="btn btn-ghost" onclick="editVehicle(${id})">Edit</button>`;
    }
  );

  $('vehiclesPage').textContent = `Page ${payload.current_page} / ${payload.last_page}`;
}

async function loadServices() {
  const category = $('serviceCategoryFilter').value;
  const query = category ? `?vehicle_category=${category}&active=1` : '?active=1';
  const res = await api(`/services${query}`);
  state.services = res.data;

  const selectedGroups = new Set(
    state.checkinItems.filter((i) => i.service_group).map((i) => i.service_group)
  );
  const vCategory = $('vehicleCategorySelect').value;

  toRows('servicesTable', ['service_id', 'service_name', 'vehicle_category', 'service_group', 'is_active'], state.services, (s) => {
    const { ok, reason } = selectedServiceRuleStatus(s, selectedGroups, vCategory);
    return `<button class="btn btn-ghost" ${ok ? '' : 'disabled'} onclick="selectService(${s.service_id})">${ok ? 'Select' : reason}</button>`;
  });

  toRows('checkinServicesTable', ['service_id', 'service_name', 'vehicle_category', 'service_group'], state.services, (s) => {
    const { ok, reason } = selectedServiceRuleStatus(s, selectedGroups, vCategory);
    return `<button class="btn btn-ghost" ${ok ? '' : 'disabled'} onclick="addCheckinService(${s.service_id})">${ok ? 'Add' : reason}</button>`;
  });
}

window.selectService = (id) => {
  if (state.selectedServiceIds.has(id)) {
    state.selectedServiceIds.delete(id);
  } else {
    state.selectedServiceIds.add(id);
  }
  toast(`Selected services: ${state.selectedServiceIds.size}`);
};

window.addCheckinService = (id) => {
  const svc = state.services.find((s) => s.service_id === id);
  if (!svc) return;

  const selectedGroups = new Set(
    state.checkinItems.filter((i) => i.service_group).map((i) => i.service_group)
  );
  const category = $('vehicleCategorySelect').value;
  const check = selectedServiceRuleStatus(svc, selectedGroups, category);
  if (!check.ok) return toast(check.reason, true);

  state.checkinItems.push({
    service_id: svc.service_id,
    item_name: svc.service_name,
    service_group: svc.service_group,
  });

  renderSelectedItems();
  loadServices();
};

function renderSelectedItems() {
  $('selectedItems').innerHTML = state.checkinItems
    .map((item, idx) => `<li>${item.service_id ? `Service #${item.service_id}` : item.item_name} (${item.price_status || 'CATALOG'}) <button class="btn btn-ghost" onclick="removeCheckinItem(${idx})">x</button></li>`)
    .join('');
}

window.removeCheckinItem = (idx) => {
  state.checkinItems.splice(idx, 1);
  renderSelectedItems();
  loadServices();
};

async function loadOrders() {
  const date = $('ordersDate').value;
  const status = $('ordersStatus').value;
  const params = new URLSearchParams();

  if (date) params.set('date', date);
  if (status) params.set('status', status);

  const res = await api(`/job-orders?${params.toString()}`);
  const payload = res.data;

  const rows = (payload.data || []).map(r => ({
  ...r,
  status: statusBadge(r.status)
}));

toRows(
  'ordersTable',
  ['job_order_id', 'customer_id', 'vehicle_id', 'washboy_name', 'payment_mode', 'status', 'created_at'],
  rows,
    (row) => {
      const orderId = row.job_order_id ?? row.id;
      return `
        <button class="btn btn-ghost" onclick="viewOrder(${orderId})">View</button>
        <button class="btn btn-ghost" onclick="updateStatus(${orderId}, 'IN_PROGRESS')">Start</button>
        <button class="btn btn-ghost" onclick="updateStatus(${orderId}, 'COMPLETED')">Complete</button>
        <button class="btn btn-ghost" onclick="updateStatus(${orderId}, 'CANCELLED')">Cancel</button>
      `;
    }
  );
}

window.viewOrder = async (id) => {
  try {
    const res = await api(`/job-orders/${id}`);
    const data = res.data || {};
    $('orderDetailOutput').textContent = JSON.stringify(data, null, 2);
    toast(`Loaded order #${id}`);
  } catch (err) {
    toast(err.message || 'Failed to load order details.', true);
  }
};

window.updateStatus = async (id, status) => {
  try {
    const payload = { status };

    if (status === 'COMPLETED') {
      payload.completed_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
    }

    await api(`/job-orders/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });

    toast(`Status updated to ${status}`);
    await loadOrders();
  } catch (err) {
    toast(err.message || 'Failed to update status.', true);
  }
};

async function loadReport() {
  const date = $('reportDate').value || new Date().toISOString().slice(0, 10);
  const res = await api(`/reports/daily?date=${date}`);
  const summary = res.data.summary || {};

  const paymentModeText = summary.by_payment_mode
    ? Object.entries(summary.by_payment_mode)
        .map(([mode, amount]) => `${mode}: ₱${amount}`)
        .join(' | ')
    : 'No data';

  const cards = [
    ['total_jobs', summary.total_jobs ?? 0],
    ['gross_total', `₱${summary.gross_total ?? 0}`],
    ['paid_total', `₱${summary.paid_total ?? 0}`],
    ['unpaid_total', `₱${summary.unpaid_total ?? 0}`],
    ['by_payment_mode', paymentModeText],
  ];

  $('reportSummary').innerHTML = cards
    .map(([k, v]) => `<article class="kpi"><h3>${k}</h3><strong>${v}</strong></article>`)
    .join('');

  toRows(
    'reportTable',
    ['job_order_id', 'customer_name', 'plate_number', 'payment_mode', 'total_amount', 'status'],
    res.data.orders || []
  );
}

window.editVehicle = (id) => {
  const vehicle = state._vehicleRows.find(
    (v) => String(v.vehicle_id ?? v.id) === String(id)
  );

  if (!vehicle) {
    return toast('Vehicle not found.', true);
  }

  $('vehicleCustomerId').value = vehicle.customer_id || '';
  $('vehiclePlateNumber').value = vehicle.plate_number || '';
  $('vehicleCategorySelect').value = vehicle.vehicle_category || 'CAR';
  $('vehicleSizeSelect').value = vehicle.vehicle_size || 'MEDIUM';
  $('vehicleTypeInput').value = vehicle.vehicle_type || '';

  state.editingVehicleId = vehicle.vehicle_id ?? vehicle.id;
  setVehicleFormMode(true);
  applyVehicleCategoryRules();
  toast('Vehicle loaded for editing');
};

function bindForms() {
  $('apiBaseInput').addEventListener('change', () => {
    state.apiBase = $('apiBaseInput').value.trim().replace(/\/$/, '');
    localStorage.setItem('apiBase', state.apiBase);
    toast('API base updated');
  });

  $('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const fd = new FormData(e.target);
      await api('/auth/login', {
        method: 'POST',
        body: JSON.stringify(Object.fromEntries(fd.entries())),
      });
      toast('Login successful');
      await loadSession();
    } catch (err) {
      toast(err.message, true);
    }
  });

  $('logoutBtn').addEventListener('click', async () => {
    try {
      await api('/auth/logout', { method: 'POST' });
    } catch {}

    state.authUser = null;
    $('sessionBadge').textContent = 'Guest';
    $('logoutBtn').classList.add('hidden');
    $('loginPanel').classList.remove('hidden');
    toast('Logged out');
  });

  $('customerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const payload = Object.fromEntries(new FormData(e.target).entries());

      if (state.editingCustomerId) {
        await api(`/customers/${state.editingCustomerId}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        });
        toast('Customer updated');
      } else {
        await api('/customers', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        toast('Customer added');
      }

      resetCustomerForm();
      await loadCustomers();
    } catch (err) {
      toast(err.message, true);
    }
  });

  $('customerCancelBtn').addEventListener('click', () => {
    resetCustomerForm();
    toast('Edit cancelled');
  });

  $('vehicleCategorySelect').addEventListener('change', applyVehicleCategoryRules);

  $('vehicleForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  try {
    const payload = Object.fromEntries(new FormData(e.target).entries());

    if (payload.vehicle_category === 'MOTOR') {
      payload.vehicle_size = null;
      payload.vehicle_type = null;
    }

    payload.customer_id = Number(payload.customer_id);

    if (state.editingVehicleId) {
      await api(`/vehicles/${state.editingVehicleId}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      toast('Vehicle updated');
    } else {
      await api('/vehicles', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      toast('Vehicle added');
    }

    resetVehicleForm();
    await loadVehicles();
  } catch (err) {
    toast(err.message, true);
  }
});

  $('vehicleCancelBtn').addEventListener('click', () => {
  resetVehicleForm();
  toast('Vehicle edit cancelled');
});

  $('leaveVehicleCheckbox')?.addEventListener('change', applyWaiverRules);

  $('customItemForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());

    if (payload.price_status === 'FIXED') {
      payload.unit_price = Number(payload.unit_price || 0);
    } else {
      delete payload.unit_price;
    }

    state.checkinItems.push(payload);
    e.target.reset();
    renderSelectedItems();
  });

  $('createJobBtn').addEventListener('click', async () => {
    try {
      const form = $('jobOrderForm');
      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());

      payload.customer_id = Number(payload.customer_id);
      payload.vehicle_id = Number(payload.vehicle_id);

      payload.leave_vehicle = form.querySelector('[name="leave_vehicle"]').checked;
      payload.waiver_accepted = form.querySelector('[name="waiver_accepted"]').checked;

      if (payload.waiver_accepted) {
        payload.waiver_accepted_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
      }

      if (payload.leave_vehicle && !payload.waiver_accepted) {
        return toast('Waiver must be accepted when leaving the vehicle in the bay.', true);
      }

      payload.items = state.checkinItems.map((item) =>
        item.service_id ? { service_id: item.service_id } : item
      );

      const res = await api('/job-orders', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      $('createJobOutput').textContent = JSON.stringify(res.data, null, 2);
      state.checkinItems = [];
      renderSelectedItems();
      form.reset();
      applyVehicleCategoryRules();
      toast('Job order created');
    } catch (err) {
      toast(err.message, true);
    }
  });

  $('loadCustomersBtn').addEventListener('click', () => loadCustomers().catch((e) => toast(e.message, true)));
  $('loadVehiclesBtn').addEventListener('click', () => loadVehicles().catch((e) => toast(e.message, true)));
  $('loadServicesBtn').addEventListener('click', () => loadServices().catch((e) => toast(e.message, true)));
  $('loadOrdersBtn').addEventListener('click', () => loadOrders().catch((e) => toast(e.message, true)));
  $('loadReportBtn').addEventListener('click', () => loadReport().catch((e) => toast(e.message, true)));

  $('quotePreviewBtn').addEventListener('click', async () => {
    try {
      const vId = Number($('quoteVehicleId').value);
      const ids = [...state.selectedServiceIds].filter(
        (id) => id !== undefined && id !== null && id !== ''
      );

      if (!vId) return toast('Please enter a valid vehicle ID.', true);
      if (ids.length === 0) return toast('Please select at least one service.', true);

      const params = new URLSearchParams({ vehicle_id: String(vId) });
      ids.forEach((id) => params.append('service_ids[]', String(id)));

      const res = await api(`/pricing/quote-preview?${params.toString()}`);
      $('quoteOutput').textContent = JSON.stringify(res.data, null, 2);
    } catch (err) {
      toast(err.message, true);
    }
  });

  $('customersPrev').addEventListener('click', () => {
    if (state.customers.page > 1) {
      state.customers.page -= 1;
      loadCustomers().catch((e) => toast(e.message, true));
    }
  });

  $('customersNext').addEventListener('click', () => {
    if (state.customers.page < state.customers.lastPage) {
      state.customers.page += 1;
      loadCustomers().catch((e) => toast(e.message, true));
    }
  });

  $('vehiclesPrev').addEventListener('click', () => {
    if (state.vehicles.page > 1) {
      state.vehicles.page -= 1;
      loadVehicles().catch((e) => toast(e.message, true));
    }
  });

  $('vehiclesNext').addEventListener('click', () => {
    if (state.vehicles.page < state.vehicles.lastPage) {
      state.vehicles.page += 1;
      loadVehicles().catch((e) => toast(e.message, true));
    }
  });
}

bindThemeToggle();
bindNav();
bindForms();
setCustomerFormMode(false);
setVehicleFormMode(false);
applyVehicleCategoryRules();
applyWaiverRules();
loadSession();