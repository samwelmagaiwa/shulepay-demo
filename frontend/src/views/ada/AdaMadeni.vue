<template>
  <CContainer fluid class="am-page" @click="closeAllMenus">

    <!-- ── Summary stat cards ── -->
    <div class="am-stats">
      <div class="am-stat am-stat--gray">
        <div class="am-stat-label">{{ t('invoices.studentsWithInvoices') }}</div>
        <div class="am-stat-value">{{ pagination.total || groupedInvoices.length }}</div>
      </div>
      <div class="am-stat am-stat--red">
        <div class="am-stat-label">{{ t('invoices.totalDebt') }}</div>
        <div class="am-stat-value am-stat-value--red">{{ formatMoney(totalOutstanding) }}</div>
      </div>
      <div class="am-stat am-stat--green">
        <div class="am-stat-label">{{ t('invoices.collected') }}</div>
        <div class="am-stat-value am-stat-value--green">{{ formatMoney(totalCollected) }}</div>
      </div>
      <div class="am-stat am-stat--amber">
        <div class="am-stat-label">{{ t('invoices.promisedToPay') }}</div>
        <div class="am-stat-value am-stat-value--amber">
          <span v-if="promisesLoading" class="spinner-border spinner-border-sm"></span>
          <span v-else>{{ promisedCount.toLocaleString() }} {{ t('invoices.promises') }}</span>
        </div>
      </div>
    </div>

    <!-- ── Alerts ── -->
    <CAlert v-if="receiptError" color="danger" dismissible class="am-alert" @close="receiptError = ''">{{ receiptError }}</CAlert>
    <CAlert v-if="bulkPrinting" color="dark" class="am-alert d-flex align-items-center gap-2">
      <CSpinner size="sm" />
      {{ t('invoices.bulkPrintingBatch', { current: bulkBatchIndex + 1, total: bulkCount?.batch_count || 1, count: bulkCurrentBatchSize }) }}
    </CAlert>
    <CAlert v-else-if="bulkBatchIndex > 0 && bulkBatchIndex < (bulkCount?.batch_count || 0)"
            color="success" dismissible class="am-alert" @close="resetBulkPrintProgress">
      {{ t('invoices.bulkPrintBatchDone', { current: bulkBatchIndex, total: bulkCount.batch_count }) }}
    </CAlert>

    <!-- ── Sticky header ── -->
    <div class="am-sticky-header">

      <!-- Topbar -->
      <div class="am-topbar">
        <div class="am-topbar-left">
          <span class="am-count">
            {{ t('common.showing', {
              from: (pagination.total || 0) === 0 ? 0 : ((pagination.current_page || 1) - 1) * Number(perPage) + 1,
              to: Math.min((pagination.current_page || 1) * Number(perPage), pagination.total || 0),
              total: pagination.total || 0,
            }) }}
          </span>
          <select v-model="perPage" @change="fetchData(1)" class="am-perpage">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="am-count">{{ t('common.perPage') }}</span>
          <button class="am-filter-btn" :class="{ active: showFilters || hasActiveFilters }" @click.stop="showFilters = !showFilters">
            ☰ Filter<span v-if="hasActiveFilters" class="am-filter-dot"></span>
          </button>
        </div>
        <div class="am-topbar-right">
          <CButton color="warning" size="sm" @click="showSmsModal = true" class="am-action-btn">
            <CIcon icon="cilSend" class="me-1" /> {{ t('invoices.sendSms') }}
          </CButton>
          <CButton color="success" size="sm" @click="showGenerateModal = true" class="am-action-btn">
            <CIcon icon="cilPlus" class="me-1" /> {{ t('invoices.generate') }}
          </CButton>
          <div v-if="pagination.last_page > 1" class="am-pager">
            <button class="am-page-btn" :disabled="(pagination.current_page || 1) <= 1" @click="goPage((pagination.current_page || 1) - 1)">{{ t('common.prev') }}</button>
            <button v-for="p in pageNumbers" :key="p" class="am-page-btn" :class="{ active: p === pagination.current_page }" @click="goPage(p)">{{ p }}</button>
            <button class="am-page-btn" :disabled="(pagination.current_page || 1) >= pagination.last_page" @click="goPage((pagination.current_page || 1) + 1)">{{ t('common.next') }}</button>
          </div>
        </div>
      </div>

      <!-- Collapsible filter panel -->
      <div v-if="showFilters" class="am-filters">
        <CFormSelect v-model="filters.school_id" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.class_id" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('invoices.allClasses') }}</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.term_number" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('invoices.allTerms') }}</option>
          <option value="1">{{ t('invoices.term1') }}</option>
          <option value="2">{{ t('invoices.term2') }}</option>
          <option value="3">{{ t('invoices.term3') }}</option>
          <option value="4">{{ t('invoices.term4') }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('invoices.allStatuses') }}</option>
          <option value="unpaid">{{ t('invoices.statusFull.unpaid') }}</option>
          <option value="partial">{{ t('invoices.statusFull.partial') }}</option>
          <option value="paid">{{ t('invoices.statusFull.paid') }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.search" :placeholder="t('invoices.searchStudent')" @input="debouncedFetch" class="am-filter-inp" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters" class="am-filter-reset">{{ t('common.reset') }}</CButton>
        <CButton color="secondary" variant="outline" size="sm" @click="showOrphanModal = true" class="am-filter-reset">{{ t('orphanInvoices.openButton') }}</CButton>
        <CButton
          v-if="filters.status === 'unpaid' || filters.status === 'partial'"
          color="dark" variant="outline" size="sm"
          :disabled="bulkPrinting || bulkCountLoading || !bulkCount?.count"
          @click="printBulkByStatus" class="am-filter-reset"
        >
          <CSpinner v-if="bulkPrinting || bulkCountLoading" size="sm" class="me-1" />
          <span v-else>🖨️ </span>
          <template v-if="bulkBatchIndex > 0 && bulkBatchIndex < (bulkCount?.batch_count || 0)">
            {{ t('invoices.printNextBatch', { current: bulkBatchIndex + 1, total: bulkCount.batch_count }) }}
          </template>
          <template v-else>
            {{ t('invoices.printByStatus', { status: t('invoices.statusFull.' + filters.status) }) }}
            <span v-if="bulkCount?.count">({{ bulkCount.count }})</span>
          </template>
        </CButton>
      </div>

      <!-- Action toolbar -->
      <div class="am-toolbar">
        <div class="am-toolbar-actions">
          <button class="am-tb-btn am-tb-view"  :disabled="!selectedRow" @click="selectedRow && openDrawer(selectedRow.student)">🔍 {{ t('common.view') }}</button>
          <button class="am-tb-btn am-tb-pay"   :disabled="!selectedRow || selectedRow.status === 'paid'" @click="selectedRow && selectedRow.status !== 'paid' && openPayment(selectedRow)">💳 {{ t('invoices.payNow') }}</button>
          <button class="am-tb-btn am-tb-print" :disabled="!selectedRow" @click="selectedRow && printStatement(selectedRow.student?.id)">🖨 {{ t('invoices.printAllReceipt') }}</button>
        </div>
        <span class="am-toolbar-hint">{{ t('common.tableHint') }}</span>
      </div>

    </div><!-- /am-sticky-header -->

    <!-- ── Grid table ── -->
    <div class="am-grid-wrap">
      <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
      <template v-else>
        <div class="am-grid">
          <!-- Header -->
          <div class="am-head">
            <div class="am-cell">{{ t('invoices.invoiceNo') }}</div>
            <div class="am-cell">{{ t('invoices.student') }}</div>
            <div class="am-cell">{{ t('invoices.class') }}</div>
            <div class="am-cell">{{ t('invoices.term') }}</div>
            <div class="am-cell">{{ t('common.total') }}</div>
            <div class="am-cell">{{ t('invoices.amountPaid') }}</div>
            <div class="am-cell">{{ t('invoices.debt') }}</div>
            <div class="am-cell">{{ t('common.status') }}</div>
            <div class="am-cell">{{ t('common.actions') }}</div>
          </div>
          <!-- Rows -->
          <div
            v-for="(group, idx) in groupedInvoices"
            :key="group.primary.id"
            class="am-row"
            :class="[rowStatusClass(group.primary), { 'am-row--selected': selectedRowId === group.primary.id, 'am-row--alt': idx % 2 === 1 }]"
            @click="selectRow(group.primary)"
            @dblclick="openDrawer(group.primary.student)"
            @contextmenu.prevent="openCtxMenu(group.primary, $event)"
          >
            <div class="am-cell am-mono">{{ group.primary.invoice_number }}</div>
            <div class="am-cell am-student-cell">
              <span class="am-name">{{ group.primary.student?.full_name }}</span>
              <CDropdown v-if="group.others.length" variant="btn-group" class="am-more-dd" teleport>
                <CDropdownToggle size="sm" color="secondary" variant="outline" class="am-more-btn">
                  +{{ group.others.length }} {{ t('invoices.moreInvoices') }}
                </CDropdownToggle>
                <CDropdownMenu style="min-width:290px;">
                  <CDropdownHeader>{{ t('invoices.otherInvoicesFor', { name: group.primary.student?.full_name }) }}</CDropdownHeader>
                  <CDropdownItem v-for="inv in group.others" :key="inv.id" style="cursor:default;" class="py-2">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                      <div>
                        <div class="small fw-semibold">{{ inv.term?.name }} — {{ inv.invoice_number }}</div>
                        <div class="small d-flex align-items-center gap-1">
                          <StatusBadge :status="inv.status" />
                          <span :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">{{ formatMoney(inv.balance_due_cents) }}</span>
                        </div>
                      </div>
                      <div class="d-flex gap-1">
                        <CButton size="sm" color="info" variant="outline" @click.stop="openDrawer(inv.student)" style="min-height:28px; min-width:28px;"><CIcon icon="cilMagnifyingGlass" /></CButton>
                        <CButton v-if="inv.status !== 'paid'" size="sm" color="primary" @click.stop="openPayment(inv)" style="min-height:28px;">{{ t('invoices.payNow') }}</CButton>
                      </div>
                    </div>
                  </CDropdownItem>
                </CDropdownMenu>
              </CDropdown>
            </div>
            <div class="am-cell">{{ group.primary.student?.school_class?.name || '—' }}</div>
            <div class="am-cell am-term-cell">
              <template v-if="group.debtTerms.length">
                <span v-for="t in group.debtTerms" :key="t.label"
                      :class="['am-term-badge', 'am-term-badge--' + t.status]">{{ t.label }}</span>
              </template>
              <span v-else class="am-term-all-paid">✓ All paid</span>
            </div>
            <div class="am-cell">{{ formatMoney(group.totalAmount) }}</div>
            <div class="am-cell am-paid">{{ formatMoney(group.totalPaid) }}</div>
            <div class="am-cell" :class="group.totalDebt > 0 ? 'am-debt' : 'am-zerodebt'">
              {{ formatMoney(group.totalDebt) }}
            </div>
            <div class="am-cell"><StatusBadge :status="group.primary.status" /></div>
            <div class="am-cell am-actions-cell">
              <button class="am-dots-btn" @click.stop="openActionMenu(group.primary, group.studentId, $event)" title="Actions">⋮</button>
            </div>
          </div>
          <div v-if="groupedInvoices.length === 0" class="am-empty">{{ t('invoices.noInvoices') }}</div>
        </div>
      </template>
    </div>

    <!-- Right-click context menu -->
    <Teleport to="body">
      <div v-if="ctxRow && ctxPos" class="am-ctx-menu" :style="{ top: ctxPos.top + 'px', left: ctxPos.left + 'px' }" @click.stop>
        <button class="am-ctx-item" @click="openDrawer(ctxRow.student); ctxRow = null">🔍 {{ t('common.view') }}</button>
        <button v-if="ctxRow.status !== 'paid'" class="am-ctx-item" @click="openPayment(ctxRow); ctxRow = null">💳 {{ t('invoices.payNow') }}</button>
        <button class="am-ctx-item" @click="printStatement(ctxRow.student?.id); ctxRow = null">🖨 {{ t('invoices.printAllReceipt') }}</button>
      </div>
    </Teleport>

    <!-- Dots-button action menu -->
    <Teleport to="body">
      <div v-if="actionMenuRow && actionMenuPos" class="am-ctx-menu" :style="{ top: actionMenuPos.top + 'px', left: actionMenuPos.left + 'px' }" @click.stop>
        <button class="am-ctx-item" @click="openDrawer(actionMenuRow.student); actionMenuRow = null">🔍 {{ t('common.view') }}</button>
        <button v-if="actionMenuRow.status !== 'paid'" class="am-ctx-item" @click="openPayment(actionMenuRow); actionMenuRow = null">💳 {{ t('invoices.payNow') }}</button>
        <button class="am-ctx-item" @click="printStatement(actionMenuStudentId); actionMenuRow = null">🖨 {{ t('invoices.printAllReceipt') }}</button>
      </div>
    </Teleport>

    <LipiaModal :visible="showPayModal" :invoice="selectedInvoice" @close="closePayModal" @paid="onPaid" />
    <MwanafunziDrawer v-if="drawerStudent" :student="drawerStudent" @close="drawerStudent = null" />
    <OrphanedInvoicesModal v-model:visible="showOrphanModal" @purged="fetchData(1)" />
    <GenerateInvoiceModal :visible="showGenerateModal" @close="showGenerateModal = false" @generated="onGenerated" />
    <SmsBlastModal :visible="showSmsModal" :student-ids="debtorIds" @close="showSmsModal = false" />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInvoicesStore } from '@/stores/invoices'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import StatusBadge           from '@/components/StatusBadge.vue'
import LipiaModal            from '@/components/LipiaModal.vue'
import MwanafunziDrawer      from '@/components/MwanafunziDrawer.vue'
import GenerateInvoiceModal  from '@/components/GenerateInvoiceModal.vue'
import SmsBlastModal         from '@/components/SmsBlastModal.vue'
import OrphanedInvoicesModal from '@/components/OrphanedInvoicesModal.vue'
import api                   from '@/services/api'
import { printStudentStatement as printStudentStatementPdf, printBulkInvoices, cleanupReceiptFrame } from '@/utils/receipt'

const { t } = useI18n()
const invoicesStore = useInvoicesStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters          = ref({ school_id: schoolStore.activeSchoolId || '', class_id: '', term_number: '', status: '', search: '' })
const showFilters      = ref(false)
const hasActiveFilters = computed(() =>
  !!(filters.value.class_id || filters.value.term_number || filters.value.status || filters.value.search)
)
const selectedRowId       = ref(null)
const selectedRow         = ref(null)
const ctxRow              = ref(null)
const ctxPos              = ref(null)
const actionMenuRow       = ref(null)
const actionMenuPos       = ref(null)
const actionMenuStudentId = ref(null)
const selectedInvoice     = ref(null)
const showPayModal        = ref(false)
const drawerStudent       = ref(null)
const showGenerateModal   = ref(false)
const showOrphanModal     = ref(false)
const showSmsModal        = ref(false)
const classes             = ref([])
const promisedCount       = ref(0)
const promisesLoading     = ref(false)
const perPage             = ref('20')
let   debounceTimer       = null

function selectRow(inv) {
  selectedRowId.value = inv.id
  selectedRow.value   = inv
}

function rowStatusClass(inv) {
  if (inv.status === 'paid')    return 'am-row--paid'
  if (inv.status === 'partial') return 'am-row--partial'
  if (inv.status === 'unpaid')  return 'am-row--unpaid'
  return ''
}

function openActionMenu(inv, studentId, event) {
  actionMenuRow.value       = inv
  actionMenuStudentId.value = studentId
  const rect      = event.currentTarget.getBoundingClientRect()
  const menuWidth = 185
  actionMenuPos.value = { top: rect.bottom + 4, left: rect.right - menuWidth }
  requestAnimationFrame(() => {
    const els = document.querySelectorAll('.am-ctx-menu')
    const el  = els[els.length - 1]
    if (el) {
      const h = el.offsetHeight
      if (rect.bottom + 4 + h > window.innerHeight)
        actionMenuPos.value = { ...actionMenuPos.value, top: rect.top - h - 4 }
    }
  })
}

function openCtxMenu(inv, event) {
  ctxRow.value = inv
  const x = event.clientX, y = event.clientY
  ctxPos.value = { top: y + 4, left: x - 175 }
  requestAnimationFrame(() => {
    const el = document.querySelector('.am-ctx-menu')
    if (el) {
      const h = el.offsetHeight
      if (y + 4 + h > window.innerHeight) ctxPos.value = { ...ctxPos.value, top: y - h - 4 }
    }
  })
}

function closeAllMenus() {
  ctxRow.value        = null
  actionMenuRow.value = null
}

function resetFilters() {
  filters.value = { school_id: schoolStore.activeSchoolId || '', class_id: '', term_number: '', status: '', search: '' }
  fetchData(1)
}

const invoices   = computed(() => invoicesStore.invoices)
const loading    = computed(() => invoicesStore.loading)
const pagination = computed(() => invoicesStore.pagination || {})
const schools    = computed(() => schoolsStore.schools)

watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  filters.value.class_id  = ''
  fetchData(1)
  loadClasses()
})

async function loadClasses() {
  try {
    const { data: cd } = await api.get('/school-classes', {
      params: filters.value.school_id ? { school_id: filters.value.school_id } : {},
    })
    classes.value = cd.data || cd
  } catch { classes.value = [] }
}

const totalOutstanding = computed(() =>
  invoices.value.reduce((s, i) => s + (i.balance_due_cents || 0), 0)
)
const totalCollected = computed(() =>
  invoices.value.reduce((s, i) => s + (i.paid_cents || 0), 0)
)
const debtorIds = computed(() =>
  invoices.value.filter(i => i.status !== 'paid').map(i => i.student?.id).filter(Boolean)
)
const pageNumbers = computed(() => {
  const total = pagination.value.last_page || 1
  const cur   = pagination.value.current_page || 1
  const pages = []
  for (let i = Math.max(1, cur - 2); i <= Math.min(total, cur + 2); i++) pages.push(i)
  return pages
})

// "FOURTH TERM" → "T4", "FIRST TERM" → "T1", "TERM 2" → "T2", etc.
function termShort(name) {
  if (!name) return '?'
  const wordMap = { first: 1, second: 2, third: 3, fourth: 4, fifth: 5 }
  const lower = name.toLowerCase()
  for (const [word, n] of Object.entries(wordMap)) {
    if (lower.includes(word)) return 'T' + n
  }
  const m = lower.match(/\d+/)
  return m ? 'T' + m[0] : name.slice(0, 2).toUpperCase()
}

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

const groupedInvoices = computed(() => {
  const byStudent = new Map()
  for (const inv of invoices.value) {
    const sid = inv.student?.id
    if (sid == null) continue
    if (!byStudent.has(sid)) byStudent.set(sid, [])
    byStudent.get(sid).push(inv)
  }
  const statusRank = { unpaid: 0, partial: 1, paid: 2 }
  return Array.from(byStudent.values()).map(group => {
    const sorted = [...group].sort((a, b) => {
      const r = (statusRank[a.status] ?? 3) - (statusRank[b.status] ?? 3)
      return r !== 0 ? r : (b.balance_due_cents || 0) - (a.balance_due_cents || 0)
    })
    const totalDebt   = sorted.reduce((s, i) => s + (i.balance_due_cents  || 0), 0)
    const totalPaid   = sorted.reduce((s, i) => s + (i.paid_cents          || 0), 0)
    const totalAmount = sorted.reduce((s, i) => s + (i.total_amount_cents  || 0), 0)
    // compact term labels for each invoice that still has debt
    const debtTerms   = sorted
      .filter(i => i.status !== 'paid')
      .map(i => ({ label: termShort(i.term?.name), status: i.status }))
    return { primary: sorted[0], others: sorted.slice(1), studentId: sorted[0].student.id, totalDebt, totalPaid, totalAmount, debtTerms }
  })
})

async function fetchData(page) {
  const params = {
    page:             page ?? pagination.value.current_page ?? 1,
    per_page:         perPage.value,
    group_by_student: 1,
  }
  if (filters.value.school_id)   params.school_id        = filters.value.school_id
  if (filters.value.class_id)    params.school_class_id  = filters.value.class_id
  if (filters.value.term_number) params.term_number       = filters.value.term_number
  if (filters.value.status)      params.status            = filters.value.status
  if (filters.value.search)      params.search            = filters.value.search
  await invoicesStore.fetchInvoices(params)
}

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchData(1), 350)
}

function goPage(p) {
  invoicesStore.pagination.current_page = p
  fetchData()
}

function openPayment(inv) {
  selectedInvoice.value = inv
  showPayModal.value    = true
}
function openDrawer(student) { if (student) drawerStudent.value = student }

const receiptError         = ref('')
const printingStatementFor = ref(null)

async function printStatement(studentId) {
  if (!studentId) return
  printingStatementFor.value = studentId
  receiptError.value         = ''
  try {
    await printStudentStatementPdf(studentId)
  } catch (e) {
    receiptError.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally {
    printingStatementFor.value = null
  }
}

// ── Bulk print ───────────────────────────────────────────────────────────────
const bulkPrinting         = ref(false)
const bulkCountLoading     = ref(false)
const bulkCount            = ref(null)
const bulkBatchIndex       = ref(0)
const bulkCurrentBatchSize = ref(0)
let   bulkCountTimer       = null

function bulkParams() {
  const params = { status: filters.value.status }
  if (filters.value.school_id)   params.school_id        = filters.value.school_id
  if (filters.value.class_id)    params.school_class_id  = filters.value.class_id
  if (filters.value.term_number) params.term_number       = filters.value.term_number
  return params
}

function resetBulkPrintProgress() { bulkBatchIndex.value = 0 }

async function fetchBulkCount() {
  resetBulkPrintProgress()
  if (filters.value.status !== 'unpaid' && filters.value.status !== 'partial') {
    bulkCount.value = null; return
  }
  bulkCountLoading.value = true
  try {
    const { data } = await api.get('/invoices/bulk-receipt/count', { params: bulkParams() })
    bulkCount.value = data
  } catch { bulkCount.value = null }
  finally { bulkCountLoading.value = false }
}

function debouncedBulkCount() {
  clearTimeout(bulkCountTimer)
  bulkCountTimer = setTimeout(fetchBulkCount, 300)
}

watch(() => [filters.value.status, filters.value.school_id, filters.value.class_id, filters.value.term_number], debouncedBulkCount)

async function printBulkByStatus() {
  if (bulkPrinting.value || !bulkCount.value?.count) return
  bulkPrinting.value = true
  receiptError.value = ''
  try {
    const batchSize = bulkCount.value.batch_size
    const offset    = bulkBatchIndex.value * batchSize
    bulkCurrentBatchSize.value = Math.min(batchSize, bulkCount.value.count - offset)
    await printBulkInvoices({ ...bulkParams(), offset, limit: batchSize })
    bulkBatchIndex.value += 1
    if (bulkBatchIndex.value >= bulkCount.value.batch_count) await fetchBulkCount()
  } catch (e) {
    receiptError.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally { bulkPrinting.value = false }
}

function closePayModal() {
  showPayModal.value    = false
  selectedInvoice.value = null
}
function onPaid() {
  showPayModal.value    = false
  selectedInvoice.value = null
  fetchData()
}
function onGenerated() {
  showGenerateModal.value = false
  fetchData()
}

async function fetchPromisedCount() {
  promisesLoading.value = true
  try {
    const params = { per_page: 1, status: 'pending' }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    const { data } = await api.get('/payment-promises', { params })
    promisedCount.value = data.total ?? data.meta?.total ?? 0
  } catch {}
  finally { promisesLoading.value = false }
}

watch(() => filters.value.school_id, () => fetchPromisedCount())

onMounted(async () => {
  document.addEventListener('click', closeAllMenus)
  try {
    await schoolsStore.fetchSchools()
    await loadClasses()
    await Promise.all([fetchData(), fetchPromisedCount()])
  } catch (e) { console.error('AdaMadeni mount error', e) }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllMenus)
  cleanupReceiptFrame()
  clearTimeout(bulkCountTimer)
})
</script>

<style scoped>
/* ── Page ── */
.am-page { padding: 8px 12px; font-size: 13px; }

/* ── Stat cards ── */
.am-stats { display: flex; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.am-stat { flex: 1; min-width: 160px; padding: 10px 14px; border-radius: 6px; border-width: 2px; border-style: solid; }
.am-stat--gray  { border-color: #6c757d; background: rgba(108,117,125,.07); box-shadow: 0 4px 12px rgba(108,117,125,.2); }
.am-stat--red   { border-color: #dc3545; background: rgba(220,53,69,.07);   box-shadow: 0 4px 12px rgba(220,53,69,.2); }
.am-stat--green { border-color: #198754; background: rgba(25,135,84,.07);   box-shadow: 0 4px 12px rgba(25,135,84,.2); }
.am-stat--amber { border-color: #d97706; background: rgba(255,193,7,.1);    box-shadow: 0 4px 12px rgba(217,119,6,.2); }
.am-stat-label { font-size: 12px; color: #6c757d; margin-bottom: 2px; }
.am-stat-value { font-size: 18px; font-weight: 700; color: #1a2a3a; }
.am-stat-value--red   { color: #dc3545; }
.am-stat-value--green { color: #198754; }
.am-stat-value--amber { color: #b45309; }
.am-alert { margin-bottom: 6px; }

/* ── Sticky header ── */
.am-sticky-header { position: sticky; top: 0; z-index: 100; background: #f4f6f8; }

/* ── Topbar ── */
.am-topbar {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 8px; padding: 6px 4px 4px;
}
.am-topbar-left  { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #4a5568; }
.am-topbar-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.am-count { white-space: nowrap; }
.am-perpage {
  padding: 2px 4px; font-size: 13px; border: 1px solid #c8d3e0;
  border-radius: 3px; background: #fff; cursor: pointer;
}
.am-action-btn { white-space: nowrap; }
.am-filter-btn {
  position: relative; border: 1px solid #c8d3e0; border-radius: 4px;
  padding: 3px 10px; font-size: 13px; background: #fff; cursor: pointer;
  color: #1a2a3a; white-space: nowrap; transition: background .1s, border-color .1s;
}
.am-filter-btn:hover, .am-filter-btn.active { background: #e8f0fe; border-color: #1565c0; color: #1565c0; font-weight: 600; }
.am-filter-dot { position: absolute; top: 3px; right: 3px; width: 6px; height: 6px; border-radius: 50%; background: #e53935; }
.am-pager { display: flex; gap: 2px; }
.am-page-btn {
  background: #fff; border: 1px solid #c8d3e0; color: #1a2a3a;
  padding: 3px 9px; font-size: 12px; border-radius: 3px; cursor: pointer;
}
.am-page-btn:hover:not(:disabled) { background: #e8f0fe; }
.am-page-btn.active { background: #1565c0 !important; color: #fff !important; border-color: #1565c0 !important; }
.am-page-btn:disabled { opacity: .45; cursor: default; }

/* ── Filter panel ── */
.am-filters {
  display: flex; align-items: center; gap: 6px; padding: 6px 4px 5px;
  flex-wrap: nowrap; background: #eef2f7; border-bottom: 1px solid #c8d3e0;
  overflow-x: auto;
}
.am-filter-sel  { flex: 1; min-width: 0; width: auto !important; font-size: 13px; }
.am-filter-inp  { flex: 1.2; min-width: 0; width: auto !important; font-size: 13px; }
.am-filter-reset { flex-shrink: 0; white-space: nowrap; font-size: 13px; }

/* ── Action toolbar ── */
.am-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 5px 8px; background: #f0f4f8; border: 1px solid #c8d3e0;
  border-bottom: none; border-radius: 4px 4px 0 0; gap: 8px; flex-wrap: wrap;
}
.am-toolbar-actions { display: flex; gap: 4px; flex-wrap: wrap; }
.am-tb-btn {
  border: 1px solid #b0bec5; border-radius: 3px; padding: 4px 12px;
  font-size: 13px; cursor: pointer; background: #fff; white-space: nowrap;
  transition: background .1s, opacity .1s;
}
.am-tb-btn:disabled { opacity: .38; cursor: default; }
.am-tb-view:not(:disabled):hover  { background: #e3f2fd; }
.am-tb-pay:not(:disabled):hover   { background: #e8f5e9; }
.am-tb-print:not(:disabled):hover { background: #fff8e1; }
.am-toolbar-hint { font-size: 11px; color: #90a4ae; white-space: nowrap; }

/* ── Grid ── */
.am-grid-wrap { border: 1px solid #c8d3e0; border-radius: 0 0 4px 4px; background: #fff; overflow: visible; }
.am-grid      { display: flex; flex-direction: column; overflow: visible; font-size: 13px; }

.am-head, .am-row {
  display: grid;
  grid-template-columns:
    minmax(120px, 1.4fr)
    minmax(140px, 2fr)
    minmax(80px,  1fr)
    minmax(90px,  1.1fr)
    minmax(90px,  1fr)
    minmax(90px,  1fr)
    minmax(90px,  1fr)
    70px
    72px;
  align-items: center;
  border-bottom: 1px solid #d0dae6;
}

/* ── Blue header ── */
.am-head {
  background: #1565c0 !important;
  color: #fff !important;
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .03em;
  user-select: none;
  border-bottom: 2px solid #0d47a1;
  position: sticky;
  top: 0;
  z-index: 40;
}
.am-head .am-cell {
  color: #fff !important;
  border-right-color: rgba(255,255,255,.2) !important;
}

.am-cell {
  padding: 5px 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  border-right: 1px solid #c8d3e0;
  line-height: 1.3;
}
.am-cell:last-child { border-right: none; }

/* ── Row variants ── */
.am-row { cursor: pointer; background: #fff; transition: background .07s; }
.am-row--alt                          { background: #e8f0fb; }
.am-row--unpaid                       { background: #fff5f5; }
.am-row--unpaid.am-row--alt           { background: #ffecec; }
.am-row--partial                      { background: #fffef0; }
.am-row--partial.am-row--alt          { background: #fffce5; }
.am-row--paid                         { background: #f0fbf4; }
.am-row--paid.am-row--alt             { background: #e4f7ec; }
.am-row--selected                     { background: #1565c0 !important; color: #fff !important; }
.am-row:hover:not(.am-row--selected) { background: #cce0ff !important; }

.am-row--selected .am-cell   { color: #fff !important; }
.am-row--selected .am-paid,
.am-row--selected .am-debt,
.am-row--selected .am-zerodebt { color: rgba(255,255,255,.9) !important; }

.am-mono     { font-family: 'Consolas','Courier New',monospace; font-size: 12px; color: #0d47a1; }
.am-row--selected .am-mono { color: #fff; }
.am-name     { font-weight: 600; }
.am-paid     { color: #1b7a3e; }
.am-debt     { color: #c62828; font-weight: 700; }
.am-zerodebt { color: #1b7a3e; font-weight: 600; }

/* ── Term badges ── */
.am-term-cell { display: flex; align-items: center; gap: 3px; flex-wrap: wrap; overflow: visible; }
.am-term-badge {
  display: inline-block; padding: 1px 6px; border-radius: 4px;
  font-size: 11px; font-weight: 700; white-space: nowrap;
}
.am-term-badge--unpaid  { background: #cf222e; color: #fff; }
.am-term-badge--partial { background: #d97706; color: #fff; }
.am-term-all-paid { font-size: 11px; color: #1b7a3e; font-weight: 600; }
.am-row--selected .am-term-badge { opacity: .9; }
.am-row--selected .am-term-all-paid { color: rgba(255,255,255,.9); }

.am-student-cell { display: flex; align-items: center; gap: 4px; overflow: visible; }
.am-more-dd      { flex-shrink: 0; }
:deep(.am-more-btn) { font-size: 11px !important; padding: 1px 5px !important; min-height: 22px !important; }

/* ── Dots button ── */
.am-actions-cell { display: flex; align-items: center; justify-content: center; overflow: visible; }
.am-dots-btn {
  border: 1px solid #c8d3e0; border-radius: 4px; padding: 2px 8px;
  font-size: 18px; font-weight: 700; line-height: 1; cursor: pointer;
  background: #fff; color: #1a2a3a; transition: background .1s;
}
.am-dots-btn:hover { background: #e8f0fe; border-color: #1565c0; }
.am-row--selected .am-dots-btn { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.4); color: #fff; }

.am-empty { padding: 32px; text-align: center; color: #6c757d; font-size: 14px; }
:deep(.dropdown-menu) { z-index: 1060; }
</style>

<style>
.am-ctx-menu {
  position: fixed; background: #fff; border: 1px solid #c8d3e0;
  border-radius: 5px; box-shadow: 0 6px 20px rgba(0,0,0,.18);
  z-index: 9999; min-width: 175px; padding: 3px;
  display: flex; flex-direction: column;
}
.am-ctx-item {
  background: none; border: none; text-align: left;
  padding: 7px 12px; font-size: 13px; cursor: pointer;
  border-radius: 3px; color: #1a2a3a; white-space: nowrap; width: 100%;
}
.am-ctx-item:hover { background: #eaf2ff; }
</style>
