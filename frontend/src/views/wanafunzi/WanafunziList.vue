<template>
  <div class="wl-shell">

    <!-- ── Toolbar ── -->
    <div class="wl-toolbar">
      <div class="wl-filters">
        <input
          v-model="filters.search"
          class="wl-input"
          :placeholder="t('students.searchPlaceholder')"
          @input="debouncedFetch"
        />
        <select v-model="filters.school_id" class="wl-select" @change="page = 1; fetchData()">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="filters.status" class="wl-select" @change="page = 1; fetchData()">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="active">{{ t('students.statuses.active') }}</option>
          <option value="sponsored">{{ t('students.statuses.sponsored') }}</option>
          <option value="half_sponsored">{{ t('students.statuses.half_sponsored') }}</option>
          <option value="orphaned">{{ t('students.statuses.orphaned') }}</option>
          <option value="transferred">{{ t('students.statuses.transferred') }}</option>
          <option value="graduated">{{ t('students.statuses.graduated') }}</option>
          <option value="dropped">{{ t('students.statuses.dropped') }}</option>
        </select>
        <select v-model="filters.has_debt" class="wl-select" @change="page = 1; fetchData()">
          <option value="">{{ t('students.allPaymentStatus') }}</option>
          <option value="1">{{ t('students.hasDebt') }}</option>
          <option value="partial">{{ t('students.partialPaid') }}</option>
          <option value="0">{{ t('students.noDebt') }}</option>
        </select>
        <button class="wl-btn-reset" @click="resetFilters">{{ t('common.reset') }}</button>
      </div>

      <div class="wl-actions">
        <span class="wl-count">{{ meta.total }} {{ t('students.students') }}</span>
        <select v-model="perPage" class="wl-select wl-select--sm" @change="onPerPageChange">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <button class="wl-btn-add" @click="showAddModal = true">+ {{ t('students.add') }}</button>
      </div>
    </div>

    <!-- ── Grid table ── -->
    <div class="wl-table-wrap">
      <div v-if="studentsStore.loading" class="wl-loading">
        <CSpinner color="primary" />
      </div>

      <table v-else class="wl-table">
        <thead>
          <tr>
            <th>{{ t('students.admission') }}</th>
            <th>{{ t('students.fullName') }}</th>
            <th>{{ t('common.class') }}</th>
            <th>{{ t('students.gender') }}</th>
            <th>{{ t('common.status') }}</th>
            <th>{{ t('students.debt') }}</th>
            <th class="wl-col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in studentsStore.students"
            :key="s.id"
            :class="{ 'wl-row--selected': selectedStudent?.id === s.id }"
            @click="openDetail(s)"
          >
            <td class="wl-cell--mono">{{ s.admission_number }}</td>
            <td class="wl-cell--name">{{ s.full_name }}</td>
            <td>{{ s.school_class?.name || '—' }}</td>
            <td>{{ s.gender === 'male' || s.gender === 'me' ? t('students.male') : s.gender === 'female' || s.gender === 'ke' ? t('students.female') : '—' }}</td>
            <td><StatusBadge :status="s.status" /></td>
            <td>
              <span v-if="!s.outstanding_balance_cents || s.outstanding_balance_cents <= 0" class="wl-paid">✓ Amelipa</span>
              <span v-else class="wl-debt">{{ formatMoney(s.outstanding_balance_cents) }}</span>
            </td>
            <td class="wl-col-actions" style="position:relative;">
              <button class="wl-btn-icon" @click.stop="activeRow = activeRow === s.id ? null : s.id">⋯</button>
              <div v-if="activeRow === s.id" class="wl-dropdown" @click.stop>
                <button @click="openDetail(s); activeRow = null">👁️ {{ t('common.view') }}</button>
                <button @click="openEdit(s); activeRow = null">✏️ {{ t('common.edit') }}</button>
                <button @click="router.push({ name: 'MwanafunziDetail', params: { id: s.id }, query: { tab: 'ahadi' } }); activeRow = null">🤝 {{ t('students.summary.recordPromise') }}</button>
                <button class="wl-dropdown--danger" @click="confirmDelete(s); activeRow = null">🗑️ {{ t('common.delete') }}</button>
              </div>
            </td>
          </tr>
          <tr v-if="!studentsStore.loading && studentsStore.students.length === 0">
            <td colspan="7" class="wl-empty">{{ t('students.noStudents') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination ── -->
    <div v-if="meta.last_page > 1" class="wl-pagination">
      <button :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; fetchData()">‹</button>
      <button
        v-for="p in visiblePages" :key="p"
        :class="{ 'wl-page--active': p === meta.current_page }"
        @click="page = p; fetchData()"
      >{{ p }}</button>
      <button :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; fetchData()">›</button>
    </div>


  </div><!-- /wl-shell -->

  <!-- Student Detail Drawer -->
  <MwanafunziDrawer v-if="selectedStudent" :student="selectedStudent" @close="selectedStudent = null" />

    <!-- Invoices left behind by the student just deleted. -->
    <OrphanedInvoicesModal v-model:visible="showOrphanModal" />

    <!-- Delete Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('students.deleteTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="mb-2">{{ t('students.confirmDeleteMsg', { name: deleteTarget?.full_name }) }}</p>

        <div v-if="previewLoading" class="text-center py-3">
          <CSpinner size="sm" />
        </div>

        <!-- What the deletion leaves behind. Invoices are no longer destroyed
             with the student, so this is a statement of what survives, not a
             warning that it is about to be lost. -->
        <template v-else-if="preview && preview.invoice_count">
          <CTable small responsive class="mb-2" style="font-size:.82rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('students.invoiceNoColumn') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.term') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('students.billedColumn') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('students.paidColumn') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="inv in preview.invoices" :key="inv.id">
                <CTableDataCell>{{ inv.invoice_number }}</CTableDataCell>
                <CTableDataCell>{{ inv.term }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ fmtCents(inv.total_cents) }}</CTableDataCell>
                <CTableDataCell class="text-end text-success">{{ fmtCents(inv.paid_cents) }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>

          <CAlert :color="preview.total_paid_cents > 0 ? 'warning' : 'info'" class="py-2 mb-0 small">
            {{ t('students.deleteKeepsInvoices', {
              invoices: preview.invoice_count,
              billed: fmtCents(preview.total_billed_cents),
              payments: preview.payment_count,
              paid: fmtCents(preview.total_paid_cents),
            }) }}
          </CAlert>
        </template>

        <CAlert v-else-if="preview" color="info" class="py-2 mb-0 small">
          {{ t('students.deleteNoInvoices') }}
        </CAlert>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

  <!-- Add / Edit Student Modal -->
  <AddStudentModal
    :visible="showAddModal || showEditModal"
    :mode="showEditModal ? 'edit' : 'create'"
    :edit-student-id="showEditModal ? editStudent?.id : null"
    @close="showAddModal = false; showEditModal = false"
    @saved="onStudentSaved"
    @registered="onStudentRegistered"
  />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { CPagination, CPaginationItem, CSpinner } from '@coreui/vue'
import { useStudentsStore } from '@/stores/students'
import api from '@/services/api'
import OrphanedInvoicesModal from '@/components/OrphanedInvoicesModal.vue'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import StatusBadge         from '@/components/StatusBadge.vue'
import MwanafunziDrawer    from '@/components/MwanafunziDrawer.vue'
import AddStudentModal     from '@/components/AddStudentModal.vue'

const { t } = useI18n()
const router = useRouter()
const studentsStore = useStudentsStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters        = ref({ search: '', school_id: '', status: '', sponsorship_type: '', has_debt: '' })
const selectedStudent  = ref(null)
const showAddModal     = ref(false)
const showEditModal    = ref(false)
const editStudent      = ref(null)
const activeRow        = ref(null)
const showDeleteModal  = ref(false)
const deleteTarget     = ref(null)
const deleting         = ref(false)
const page            = ref(1)
const perPage         = ref('20')
const meta            = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
let   debounceTimer   = null

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const schools = computed(() => schoolsStore.schools)

// Sync with nav school switcher
watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id ? String(id) : ''
  page.value = 1
  fetchData()
})

function formatMoney(cents) {
  return 'TZS ' + Number(cents / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

async function fetchData() {
  const params = { page: page.value, per_page: perPage.value }
  if (filters.value.search)    params.search    = filters.value.search
  if (filters.value.school_id) params.school_id = filters.value.school_id
  if (filters.value.status)    params.status    = filters.value.status
  if (filters.value.sponsorship_type) params.sponsorship_type = filters.value.sponsorship_type
  if (filters.value.has_debt !== '') params.has_debt = filters.value.has_debt
  await studentsStore.fetchStudents(params)
  meta.value = studentsStore.pagination || meta.value
}

function onPerPageChange() {
  page.value = 1
  fetchData()
}

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; fetchData() }, 350)
}

function resetFilters() {
  filters.value = { search: '', school_id: '', status: '', sponsorship_type: '', has_debt: '' }
  if (schoolStore.activeSchoolId) {
    filters.value.school_id = String(schoolStore.activeSchoolId)
  }
  page.value = 1
  fetchData()
}

function openDetail(student) {
  selectedStudent.value = student
}

function openEdit(student) {
  editStudent.value = student
  showEditModal.value = true
}

const showOrphanModal = ref(false)
const preview = ref(null)
const previewLoading = ref(false)

const fmtCents = (c) => 'TZS ' + Math.round((c || 0) / 100).toLocaleString()

async function confirmDelete(student) {
  deleteTarget.value = student
  preview.value = null
  showDeleteModal.value = true

  // Fetched per open rather than cached: an invoice may have been raised or paid
  // since the list was loaded, and this is the number the decision rests on.
  previewLoading.value = true
  try {
    const { data } = await api.get(`/students/${student.id}/deletion-preview`)
    preview.value = data
  } catch {
    // A failed preview must not block the delete — it is context, not a gate.
    preview.value = null
  } finally {
    previewLoading.value = false
  }
}

async function doDelete() {
  deleting.value = true
  // Captured before the request, because the preview is cleared with the modal
  // and this decides whether there is anything left to review afterwards.
  const hadInvoices = (preview.value?.invoice_count || 0) > 0
  try {
    await studentsStore.deleteStudent(deleteTarget.value.id)
    showDeleteModal.value = false
    fetchData()

    // The student is gone but their invoices are not. Open the list of invoices
    // left behind so they can be cleared now, rather than leaving the user to
    // find the screen later and remember why they wanted it.
    if (hadInvoices) showOrphanModal.value = true
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kufuta.')
  } finally {
    deleting.value = false
  }
}

// A completed registration refreshes the list but leaves the modal open on its
// confirmation card. Closing here would put the operator straight back on the
// list with nothing said, which is the ambiguity that produced duplicate
// registrations; the modal closes when they acknowledge it.
function onStudentRegistered() {
  fetchData()
}

function onStudentSaved() {
  showAddModal.value = false
  showEditModal.value = false
  fetchData()
}

function onDocClick() { activeRow.value = null }

onMounted(async () => {
  document.addEventListener('click', onDocClick)
  // Initialize school_id filter from store
  if (schoolStore.activeSchoolId) {
    filters.value.school_id = String(schoolStore.activeSchoolId)
  }
  try { await schoolsStore.fetchSchools() } catch {}
  try { await fetchData() } catch {}
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>

<style scoped>
/* ── Force pure white regardless of app theme ── */
.wl-shell, .wl-shell * {
  box-sizing: border-box;
}

.wl-shell {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  background: #ffffff !important;
  border: 1px solid #d0d7de;
  overflow: hidden;
  font-size: 12.5px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #24292f;
}

/* ── Toolbar ── */
.wl-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
  padding: 6px 10px;
  border-bottom: 1px solid #d0d7de;
  background: #f6f8fa !important;
  flex-shrink: 0;
}
.wl-filters { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.wl-actions  { display: flex; align-items: center; gap: 6px; }

.wl-input, .wl-select {
  height: 26px;
  padding: 0 7px;
  border: 1px solid #d0d7de;
  border-radius: 2px;
  font-size: 12px;
  background: #ffffff !important;
  color: #24292f !important;
  outline: none;
}
.wl-input:focus, .wl-select:focus { border-color: #0969da; box-shadow: 0 0 0 2px rgba(9,105,218,.15); }
.wl-select--sm { width: 56px; }

.wl-btn-reset {
  height: 26px;
  padding: 0 10px;
  border: 1px solid #d0d7de;
  border-radius: 2px;
  background: #ffffff !important;
  font-size: 12px;
  color: #57606a !important;
  cursor: pointer;
}
.wl-btn-reset:hover { background: #f3f4f6 !important; }

.wl-btn-add {
  height: 26px;
  padding: 0 12px;
  border: none;
  border-radius: 2px;
  background: #0969da !important;
  color: #fff !important;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}
.wl-btn-add:hover { background: #0860ca !important; }

.wl-count {
  font-size: 11.5px;
  color: #57606a !important;
  white-space: nowrap;
}

/* ── Table wrapper ── */
.wl-table-wrap {
  flex: 1;
  overflow: auto;
  background: #ffffff !important;
}

.wl-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 60px;
  background: #ffffff;
}

/* ── Table ── */
.wl-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  background: #ffffff;
}

/* sticky header */
.wl-table thead tr {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #f6f8fa !important;
}

.wl-table th {
  padding: 5px 8px;
  font-size: 11px;
  font-weight: 600;
  color: #57606a !important;
  text-transform: uppercase;
  letter-spacing: .04em;
  border-right: 1px solid #d8dee4;
  border-bottom: 1px solid #d0d7de;
  white-space: nowrap;
  text-align: left;
  background: #f6f8fa !important;
}
.wl-table th:last-child { border-right: none; }

/* body rows */
.wl-table tbody tr {
  cursor: pointer;
  border-bottom: 1px solid #eaeef2;
  background: #ffffff;
}
.wl-table tbody tr:hover {
  background: #f0f6ff !important;
}
.wl-table tbody tr.wl-row--selected {
  background: #0969da !important;
}
.wl-table tbody tr.wl-row--selected td {
  color: #ffffff !important;
  border-right-color: rgba(255,255,255,.2);
}
.wl-table tbody tr.wl-row--selected .wl-paid,
.wl-table tbody tr.wl-row--selected .wl-debt,
.wl-table tbody tr.wl-row--selected :deep(.badge),
.wl-table tbody tr.wl-row--selected :deep(.status-badge) {
  color: #ffffff !important;
  background: rgba(255,255,255,.25) !important;
  border-color: transparent !important;
}

.wl-table td {
  padding: 4px 8px;
  font-size: 12.5px;
  color: #24292f !important;
  border-right: 1px solid #eaeef2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background: transparent;
  line-height: 1.6;
}
.wl-table td:last-child { border-right: none; }

.wl-cell--mono { font-family: ui-monospace, 'Cascadia Mono', monospace; font-size: 11.5px; color: #0969da !important; }
.wl-cell--name { font-weight: 600; color: #24292f !important; }

.wl-col-actions { width: 44px; text-align: center; }

.wl-paid  { color: #1a7f37 !important; font-size: 12px; }
.wl-debt  { color: #cf222e !important; font-weight: 600; font-size: 12px; }

.wl-empty {
  text-align: center;
  color: #8c959f;
  padding: 48px;
  font-size: 13px;
  background: #ffffff;
}

/* ── Row action button ── */
.wl-btn-icon {
  width: 24px; height: 20px;
  border: 1px solid #d0d7de;
  border-radius: 2px;
  background: #ffffff !important;
  font-size: 13px;
  line-height: 1;
  cursor: pointer;
  color: #57606a !important;
}
.wl-btn-icon:hover { background: #f3f4f6 !important; }

/* ── Dropdown ── */
.wl-dropdown {
  position: absolute;
  right: 0; bottom: 100%;
  background: #ffffff !important;
  border: 1px solid #d0d7de;
  border-radius: 3px;
  box-shadow: 0 8px 24px rgba(140,149,159,.2);
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 1px;
  z-index: 200;
  min-width: 165px;
}
.wl-dropdown button {
  display: block;
  width: 100%;
  padding: 5px 10px;
  text-align: left;
  background: none !important;
  border: none;
  border-radius: 2px;
  font-size: 12px;
  color: #24292f !important;
  cursor: pointer;
}
.wl-dropdown button:hover { background: #f6f8fa !important; }
.wl-dropdown--danger { color: #cf222e !important; }

/* ── Pagination ── */
.wl-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 7px;
  border-top: 1px solid #d0d7de;
  background: #f6f8fa !important;
  flex-shrink: 0;
}
.wl-pagination button {
  min-width: 28px; height: 24px;
  padding: 0 7px;
  border: 1px solid #d0d7de;
  border-radius: 2px;
  background: #ffffff !important;
  font-size: 12px;
  color: #24292f !important;
  cursor: pointer;
}
.wl-pagination button:hover:not(:disabled) { background: #f0f6ff !important; border-color: #0969da; color: #0969da !important; }
.wl-pagination button:disabled { opacity: .35; cursor: default; }
.wl-page--active { background: #0969da !important; color: #fff !important; border-color: #0969da !important; }
</style>
