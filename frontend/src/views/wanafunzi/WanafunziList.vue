<template>
  <div class="wl-shell" @click="onShellClick">

    <!-- ── Top bar ── -->
    <div class="wl-topbar">
      <div class="wl-topbar-left">
        <span class="wl-showing">
          Showing {{ showingFrom }}–{{ showingTo }} of {{ meta.total }}
        </span>
        <select v-model="perPage" class="wl-select wl-select--sm" @change="onPerPageChange">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="wl-perpage-label">per page</span>
        <button class="wl-btn-filter" :class="{ 'wl-btn-filter--active': filtersVisible }" @click.stop="filtersVisible = !filtersVisible" title="Filters">
          &#9776; Filter
        </button>
      </div>
      <div class="wl-topbar-right">
        <button class="wl-btn-add" @click="showAddModal = true">+ {{ t('students.add') }}</button>
        <div class="wl-pagination-top">
          <button :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; fetchData()">Previous</button>
          <button
            v-for="p in visiblePages" :key="p"
            :class="{ 'wl-page--active': p === meta.current_page }"
            @click="page = p; fetchData()"
          >{{ p }}</button>
          <button :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; fetchData()">Next</button>
        </div>
      </div>
    </div>

    <!-- ── Collapsible filters ── -->
    <div v-if="filtersVisible" class="wl-filters-panel" @click.stop>
      <input v-model="filters.search" class="wl-input" :placeholder="t('students.searchPlaceholder')" @input="debouncedFetch" />
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

    <!-- ── Action bar ── -->
    <div class="wl-actionbar">
      <button class="wl-act" :disabled="!selectedStudent" @click="selectedStudent && openDetail(selectedStudent)">
        <span class="wl-act-icon">&#9679;</span> {{ t('common.view') }}
      </button>
      <button class="wl-act" :disabled="!selectedStudent" @click="selectedStudent && openEdit(selectedStudent)">
        <span class="wl-act-icon">&#9998;</span> {{ t('common.edit') }}
      </button>
      <button class="wl-act" :disabled="!selectedStudent" @click="selectedStudent && goRecordPromise(selectedStudent)">
        <span class="wl-act-icon">&#9679;</span> {{ t('students.summary.recordPromise') }}
      </button>
      <button class="wl-act wl-act--danger" :disabled="!selectedStudent" @click="selectedStudent && confirmDelete(selectedStudent)">
        <span class="wl-act-icon">&#9635;</span> {{ t('common.delete') }}
      </button>
      <span class="wl-hint">{{ t('students.dblClickHint', 'Double-click a row to open · right-click for actions') }}</span>
    </div>

    <!-- ── Grid table ── -->
    <div class="wl-table-wrap">
      <div v-if="studentsStore.loading" class="wl-loading">
        <CSpinner color="primary" />
      </div>

      <table v-else class="wl-table">
        <colgroup>
          <col style="width:170px"><!-- Full Name -->
          <col style="width:95px"> <!-- Admission -->
          <col style="width:88px"> <!-- DOB -->
          <col style="width:70px"> <!-- Class -->
          <col style="width:120px"><!-- School -->
          <col style="width:60px"> <!-- Gender -->
          <col style="width:90px"> <!-- Sponsorship -->
          <col style="width:85px"> <!-- Admitted -->
          <col style="width:100px"><!-- Outstanding -->
          <col style="width:76px"> <!-- Status -->
        </colgroup>
        <thead>
          <tr>
            <th @click="toggleSort('full_name')">
              {{ t('students.fullName') }}<span class="wl-sort">{{ sortIcon('full_name') }}</span>
            </th>
            <th @click="toggleSort('admission_number')">
              {{ t('students.admission') }}<span class="wl-sort">{{ sortIcon('admission_number') }}</span>
            </th>
            <th @click="toggleSort('date_of_birth')">
              {{ t('students.dob', 'DOB') }}<span class="wl-sort">{{ sortIcon('date_of_birth') }}</span>
            </th>
            <th @click="toggleSort('school_class')">
              {{ t('common.class') }}<span class="wl-sort">{{ sortIcon('school_class') }}</span>
            </th>
            <th @click="toggleSort('school')">
              {{ t('common.school', 'School') }}<span class="wl-sort">{{ sortIcon('school') }}</span>
            </th>
            <th @click="toggleSort('gender')">
              {{ t('students.gender') }}<span class="wl-sort">{{ sortIcon('gender') }}</span>
            </th>
            <th @click="toggleSort('sponsorship_type')">
              {{ t('students.sponsorship', 'Sponsorship') }}<span class="wl-sort">{{ sortIcon('sponsorship_type') }}</span>
            </th>
            <th @click="toggleSort('admitted_at')">
              {{ t('students.admitted', 'Admitted') }}<span class="wl-sort">{{ sortIcon('admitted_at') }}</span>
            </th>
            <th @click="toggleSort('outstanding_balance_cents')">
              {{ t('students.outstanding', 'Outstanding') }}<span class="wl-sort">{{ sortIcon('outstanding_balance_cents') }}</span>
            </th>
            <th>{{ t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in sortedStudents"
            :key="s.id"
            :class="{ 'wl-row--selected': selectedStudent?.id === s.id }"
            @click="selectRow(s)"
            @dblclick="openDetail(s)"
            @contextmenu.prevent="openContextMenu($event, s)"
          >
            <td class="wl-cell--name">{{ s.full_name }}</td>
            <td class="wl-cell--mono">{{ s.admission_number || '—' }}</td>
            <td>{{ formatDateLong(s.date_of_birth) }}</td>
            <td>{{ s.school_class?.name || '—' }}</td>
            <td class="wl-cell--school">{{ s.school?.name || '—' }}</td>
            <td>{{ genderLabel(s.gender) }}</td>
            <td>{{ sponsorshipLabel(s.sponsorship_type) }}</td>
            <td>{{ formatDateShort(s.admitted_at) }}</td>
            <td>
              <span v-if="!s.outstanding_balance_cents || s.outstanding_balance_cents <= 0" class="wl-paid">Paid up</span>
              <span v-else class="wl-debt">{{ formatMoney(s.outstanding_balance_cents) }}</span>
            </td>
            <td><span :class="['wl-badge', 'wl-badge--' + (s.status || 'active')]">{{ statusLabel(s.status) }}</span></td>
          </tr>
          <!-- filler rows to fill remaining space -->
          <tr v-for="n in fillerRows" :key="'filler-' + n" class="wl-row--filler" aria-hidden="true">
            <td colspan="10"></td>
          </tr>
          <tr v-if="!studentsStore.loading && studentsStore.students.length === 0">
            <td colspan="10" class="wl-empty">{{ t('students.noStudents') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- pagination is in top bar -->

  </div><!-- /wl-shell -->

  <!-- Context menu -->
  <div
    v-if="contextMenu.visible"
    class="wl-ctx"
    :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
    @click.stop
  >
    <button @click="openDetail(contextMenu.student); contextMenu.visible = false">👁 {{ t('common.view') }}</button>
    <button @click="openEdit(contextMenu.student); contextMenu.visible = false">✏️ {{ t('common.edit') }}</button>
    <button @click="goRecordPromise(contextMenu.student); contextMenu.visible = false">🤝 {{ t('students.summary.recordPromise') }}</button>
    <button class="wl-ctx--danger" @click="confirmDelete(contextMenu.student); contextMenu.visible = false">🗑️ {{ t('common.delete') }}</button>
  </div>

  <!-- Student Detail Drawer -->
  <MwanafunziDrawer v-if="drawerStudent" :student="drawerStudent" @close="drawerStudent = null" />

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
import { CSpinner } from '@coreui/vue'
import { useStudentsStore } from '@/stores/students'
import api from '@/services/api'
import OrphanedInvoicesModal from '@/components/OrphanedInvoicesModal.vue'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import MwanafunziDrawer    from '@/components/MwanafunziDrawer.vue'
import AddStudentModal     from '@/components/AddStudentModal.vue'

const { t } = useI18n()
const router = useRouter()
const studentsStore = useStudentsStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters        = ref({ search: '', school_id: '', status: '', sponsorship_type: '', has_debt: '' })
const selectedStudent  = ref(null)
const drawerStudent    = ref(null)
const showAddModal     = ref(false)
const showEditModal    = ref(false)
const editStudent      = ref(null)
const showDeleteModal  = ref(false)
const deleteTarget     = ref(null)
const deleting         = ref(false)
const page            = ref(1)
const perPage         = ref('20')
const meta            = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
const filtersVisible  = ref(false)
let   debounceTimer   = null

const sortKey = ref('')
const sortDir = ref('asc')

const contextMenu = ref({ visible: false, x: 0, y: 0, student: null })

const MIN_ROWS = 18

const showingFrom = computed(() => {
  if (meta.value.total === 0) return 0
  return (meta.value.current_page - 1) * Number(perPage.value) + 1
})
const showingTo = computed(() => {
  return Math.min(meta.value.current_page * Number(perPage.value), meta.value.total)
})

const fillerRows = computed(() => {
  const count = studentsStore.students.length
  return count < MIN_ROWS ? MIN_ROWS - count : 0
})

const sortedStudents = computed(() => {
  if (!sortKey.value) return studentsStore.students
  const key = sortKey.value
  const dir = sortDir.value === 'asc' ? 1 : -1
  return [...studentsStore.students].sort((a, b) => {
    let av, bv
    if (key === 'school_class') { av = a.school_class?.name || ''; bv = b.school_class?.name || '' }
    else if (key === 'school')  { av = a.school?.name || '';       bv = b.school?.name || '' }
    else                         { av = a[key] ?? '';               bv = b[key] ?? '' }
    if (av < bv) return -dir
    if (av > bv) return dir
    return 0
  })
})

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
}

function sortIcon(key) {
  if (sortKey.value !== key) return ' ↕'
  return sortDir.value === 'asc' ? ' ↑' : ' ↓'
}

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const schools = computed(() => schoolsStore.schools)

watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id ? String(id) : ''
  page.value = 1
  fetchData()
})

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

function formatMoney(cents) {
  return 'TZS ' + Number(cents / 100).toLocaleString('en', { minimumFractionDigits: 0 })
}

function formatDateLong(d) {
  if (!d) return '—'
  const parts = d.split('-')
  if (parts.length !== 3) return d
  const day = parseInt(parts[2], 10)
  const mon = MONTHS[parseInt(parts[1], 10) - 1] || parts[1]
  const yr  = parts[0]
  return `${day}-${mon}-${yr}`
}

function formatDateShort(d) {
  if (!d) return '—'
  const parts = d.split('-')
  if (parts.length !== 3) return d
  const day = parseInt(parts[2], 10)
  const mon = MONTHS[parseInt(parts[1], 10) - 1] || parts[1]
  const yr  = parts[0]
  const label = `${day}-${mon}-${yr}`
  return label.length > 12 ? label.slice(0, 12) + '...' : label
}

function genderLabel(g) {
  if (!g) return '—'
  const v = g.toLowerCase()
  if (v === 'male' || v === 'me') return 'Male'
  if (v === 'female' || v === 'ke') return 'Female'
  return g
}

function sponsorshipLabel(s) {
  if (!s || s === 'none') return 'Not Sponsored'
  const map = { full: 'Sponsored (Free)', half: 'Half Sponsored', full_paid: 'Sponsored (Paid)' }
  return map[s] || s
}

function statusLabel(s) {
  if (!s) return 'Active'
  const map = {
    active: 'Active', sponsored: 'Sponsored', half_sponsored: 'Half Sponsored',
    orphaned: 'Orphaned', transferred: 'Transferred', graduated: 'Graduated', dropped: 'Dropped',
  }
  return map[s] || s
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

function selectRow(student) {
  selectedStudent.value = selectedStudent.value?.id === student.id ? null : student
}

function openDetail(student) {
  drawerStudent.value = student
}

function openEdit(student) {
  editStudent.value = student
  showEditModal.value = true
}

function goRecordPromise(student) {
  router.push({ name: 'MwanafunziDetail', params: { id: student.id }, query: { tab: 'ahadi' } })
}

function openContextMenu(event, student) {
  selectedStudent.value = student
  contextMenu.value = { visible: true, x: event.clientX, y: event.clientY, student }
}

function onShellClick() {
  contextMenu.value.visible = false
}

const showOrphanModal = ref(false)
const preview = ref(null)
const previewLoading = ref(false)

const fmtCents = (c) => 'TZS ' + Math.round((c || 0) / 100).toLocaleString()

async function confirmDelete(student) {
  deleteTarget.value = student
  preview.value = null
  showDeleteModal.value = true

  previewLoading.value = true
  try {
    const { data } = await api.get(`/students/${student.id}/deletion-preview`)
    preview.value = data
  } catch {
    preview.value = null
  } finally {
    previewLoading.value = false
  }
}

async function doDelete() {
  deleting.value = true
  const hadInvoices = (preview.value?.invoice_count || 0) > 0
  try {
    await studentsStore.deleteStudent(deleteTarget.value.id)
    showDeleteModal.value = false
    fetchData()
    if (hadInvoices) showOrphanModal.value = true
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kufuta.')
  } finally {
    deleting.value = false
  }
}

function onStudentRegistered() {
  fetchData()
}

function onStudentSaved() {
  showAddModal.value = false
  showEditModal.value = false
  fetchData()
}

function onDocClick() {
  contextMenu.value.visible = false
}

onMounted(async () => {
  document.addEventListener('click', onDocClick)
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

/* ── Top bar ── */
.wl-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
  padding: 6px 12px;
  border-bottom: 1px solid #d0d7de;
  background: #f6f8fa !important;
  flex-shrink: 0;
}
.wl-topbar-left  { display: flex; align-items: center; gap: 6px; }
.wl-topbar-right { display: flex; align-items: center; gap: 6px; }

.wl-showing {
  font-size: 12px;
  color: #24292f !important;
  white-space: nowrap;
}
.wl-perpage-label {
  font-size: 12px;
  color: #57606a !important;
}

.wl-btn-filter {
  height: 26px;
  padding: 0 10px;
  border: 1px solid #d0d7de;
  border-radius: 2px;
  background: #ffffff !important;
  font-size: 12px;
  color: #57606a !important;
  cursor: pointer;
}
.wl-btn-filter:hover { background: #f0f6ff !important; color: #0969da !important; }
.wl-btn-filter--active { background: #dbeafe !important; border-color: #0969da; color: #0969da !important; }

/* ── Collapsible filters panel ── */
.wl-filters-panel {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  padding: 5px 12px;
  border-bottom: 1px solid #d0d7de;
  background: #f0f6ff !important;
  flex-shrink: 0;
}

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
  height: 28px;
  padding: 0 14px;
  border: none;
  border-radius: 3px;
  background: #0969da !important;
  color: #fff !important;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}
.wl-btn-add:hover { background: #0860ca !important; }

/* top-bar pagination */
.wl-pagination-top {
  display: flex;
  align-items: center;
  gap: 2px;
}
.wl-pagination-top button {
  height: 28px;
  padding: 0 10px;
  border: 1px solid #d0d7de;
  border-radius: 3px;
  background: #ffffff !important;
  font-size: 12px;
  color: #24292f !important;
  cursor: pointer;
  white-space: nowrap;
}
.wl-pagination-top button:hover:not(:disabled) { background: #f0f6ff !important; border-color: #0969da; color: #0969da !important; }
.wl-pagination-top button:disabled { opacity: .35; cursor: default; }
.wl-page--active { background: #0969da !important; color: #fff !important; border-color: #0969da !important; }

/* ── Action bar ── */
.wl-actionbar {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 4px 8px;
  border-bottom: 1px solid #d0d7de;
  background: #f6f8fa !important;
  flex-shrink: 0;
}

.wl-act {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 24px;
  padding: 0 10px;
  border: 1px solid #d0d7de;
  border-radius: 2px;
  background: #ffffff !important;
  font-size: 11.5px;
  color: #24292f !important;
  cursor: pointer;
  white-space: nowrap;
}
.wl-act:not(:disabled):hover { background: #f0f6ff !important; border-color: #0969da; color: #0969da !important; }
.wl-act:disabled { opacity: .4; cursor: default; }
.wl-act--danger:not(:disabled):hover { background: #fff0f0 !important; border-color: #cf222e; color: #cf222e !important; }
.wl-act-icon { font-size: 10px; }

.wl-hint {
  margin-left: auto;
  font-size: 11px;
  color: #8c959f !important;
  font-style: italic;
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
  border-bottom: 2px solid #d0d7de;
  white-space: nowrap;
  text-align: left;
  background: #f6f8fa !important;
  cursor: pointer;
  user-select: none;
}
.wl-table th:last-child { border-right: none; }
.wl-table th:hover { background: #eaeef2 !important; }

.wl-sort {
  color: #8c959f;
  font-size: 10px;
  font-style: normal;
}

/* body rows — alternating stripe */
.wl-table tbody tr {
  cursor: pointer;
  border-bottom: 1px solid #eaeef2;
  background: #ffffff;
}
.wl-table tbody tr:nth-child(even) {
  background: #f6f8fa;
}
.wl-table tbody tr:hover {
  background: #dbeafe !important;
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

.wl-row--filler {
  cursor: default;
  height: 22px;
}
.wl-row--filler:hover { background: #ffffff !important; }

.wl-table td {
  padding: 3px 8px;
  font-size: 12px;
  color: #24292f !important;
  border-right: 1px solid #eaeef2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background: transparent;
  line-height: 1.6;
}
.wl-table td:last-child { border-right: none; }

.wl-cell--mono  { font-family: ui-monospace, 'Cascadia Mono', monospace; font-size: 11.5px; color: #0969da !important; }
.wl-cell--name  { font-weight: 600; color: #24292f !important; }
.wl-cell--school { font-size: 11.5px; color: #57606a !important; }

.wl-paid  { color: #1a7f37 !important; font-size: 11.5px; font-weight: 500; }
.wl-debt  { color: #cf222e !important; font-weight: 600; font-size: 11.5px; }

.wl-empty {
  text-align: center;
  color: #8c959f;
  padding: 48px;
  font-size: 13px;
  background: #ffffff;
}

/* ── Context menu ── */
.wl-ctx {
  position: fixed;
  z-index: 9999;
  background: #ffffff;
  border: 1px solid #d0d7de;
  border-radius: 4px;
  box-shadow: 0 8px 24px rgba(140,149,159,.25);
  padding: 4px;
  min-width: 170px;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.wl-ctx button {
  display: block;
  width: 100%;
  padding: 5px 10px;
  text-align: left;
  background: none;
  border: none;
  border-radius: 2px;
  font-size: 12px;
  color: #24292f;
  cursor: pointer;
}
.wl-ctx button:hover { background: #f6f8fa; }
.wl-ctx--danger { color: #cf222e !important; }

/* ── Status badges ── */
.wl-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  color: #fff !important;
}
.wl-badge--active       { background: #1a7f37; }
.wl-badge--sponsored    { background: #0969da; }
.wl-badge--half_sponsored { background: #6e40c9; }
.wl-badge--orphaned     { background: #9a6700; }
.wl-badge--transferred  { background: #57606a; }
.wl-badge--graduated    { background: #0969da; }
.wl-badge--dropped      { background: #cf222e; }

.wl-table tbody tr.wl-row--selected .wl-badge {
  background: rgba(255,255,255,.3) !important;
  color: #fff !important;
}
</style>
