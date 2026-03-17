<template>
  <div class="space-y-6">
      <!-- Advanced Analytics Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-emerald-500">
          <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Sales</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(analytics.totalSales) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-rose-500">
          <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total COGS</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(analytics.totalCogs) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-cyan-500">
          <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Profit</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(analytics.totalProfit) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-indigo-500">
          <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Inventory Value</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(analytics.totalInventoryValue) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-amber-500">
          <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Orders</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statText(analytics.totalOrders) }}</p>
        </div>
      </div>

      <!-- Advanced Analytics Charts (ApexCharts) -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sales Trend (Last 30 Days)</h3>
          <div v-if="isChartsLoading" class="h-[320px] rounded-lg bg-gray-100 dark:bg-gray-700/40 animate-pulse"></div>
          <VueApexCharts v-else type="line" height="320" :options="salesTrendOptions" :series="salesTrendSeries" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Monthly Sales</h3>
          <div v-if="isChartsLoading" class="h-[320px] rounded-lg bg-gray-100 dark:bg-gray-700/40 animate-pulse"></div>
          <VueApexCharts v-else type="bar" height="320" :options="monthlySalesOptions" :series="monthlySalesSeries" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Product Category Sales</h3>
          <div v-if="isChartsLoading" class="h-[320px] rounded-lg bg-gray-100 dark:bg-gray-700/40 animate-pulse"></div>
          <VueApexCharts v-else type="donut" height="320" :options="categorySalesOptions" :series="categorySalesSeries" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Selling Products</h3>
          <div v-if="isChartsLoading" class="h-[320px] rounded-lg bg-gray-100 dark:bg-gray-700/40 animate-pulse"></div>
          <VueApexCharts v-else type="bar" height="320" :options="topSellingOptions" :series="topSellingSeries" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 xl:col-span-2">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Inventory Value by Branch</h3>
          <div v-if="isChartsLoading" class="h-[320px] rounded-lg bg-gray-100 dark:bg-gray-700/40 animate-pulse"></div>
          <VueApexCharts v-else type="bar" height="320" :options="branchInventoryOptions" :series="branchInventorySeries" />
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Products -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Products</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ statText(stats.totalProducts) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Total Inventory -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Inventory</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ statText(stats.totalInventory) }}</p>
            </div>
            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6-4a2 2 0 100-4 2 2 0 000 4z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Active Branches -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-orange-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Active Branches</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ statText(stats.activeBranches) }}</p>
            </div>
            <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Low Stock Products -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-red-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Low Stock Products</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ statText(stats.lowStockCount) }}</p>
            </div>
            <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 2.523a6 6 0 018.367 8.367zM9 13a1 1 0 100-2 1 1 0 000 2zm0-5a1 1 0 10-2 0 1 1 0 002 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <div v-if="error" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-sm text-red-600 dark:text-red-400">
        {{ error }}
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Orders per Day Chart (Chart.js) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Orders per Day (Last 14 Days)</h3>
          <div class="relative h-80">
            <div v-if="isChartsLoading" class="h-full rounded-lg bg-gray-100 dark:bg-gray-700/40 animate-pulse"></div>
            <canvas v-else ref="chartCanvas" class="max-h-80"></canvas>
          </div>
        </div>

        <!-- Top Selling Products -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Selling Products</h3>
          <div class="space-y-3">
            <div
              v-for="(product, idx) in topProducts"
              :key="idx"
              class="flex items-center justify-between py-2"
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ product.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.sold }} sold</p>
              </div>
              <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full"
                  :style="{ width: `${(product.sold / maxProductSold) * 100}%` }"
                ></div>
              </div>
            </div>
            <div v-if="!isLoading && topProducts.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
              No products found.
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Products Section -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Low Stock Inventory (Available < 10)</h3>
        <div v-if="isLoading" class="text-gray-500 dark:text-gray-400 text-sm">Loading inventory data...</div>
        <div v-else-if="lowStockProducts.length === 0" class="text-gray-500 dark:text-gray-400 text-sm">
          All products have sufficient stock.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left px-4 py-2 font-semibold text-gray-900 dark:text-white">Product Name</th>
                <th class="text-left px-4 py-2 font-semibold text-gray-900 dark:text-white">SKU</th>
                <th class="text-left px-4 py-2 font-semibold text-gray-900 dark:text-white">Branch</th>
                <th class="text-right px-4 py-2 font-semibold text-gray-900 dark:text-white">Available Qty</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in lowStockProducts"
                :key="`${item.sku_id}-${item.branch_id}`"
                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50"
              >
                <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ item.product_name }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ item.sku_code }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ item.branch_name }}</td>
                <td class="px-4 py-3 text-right">
                  <span
                    class="inline-block px-3 py-1 rounded-full text-sm font-semibold"
                    :class="
                      item.available < 5
                        ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
                        : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400'
                    "
                  >
                    {{ item.available }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import type { ChartOptions } from 'chart.js/auto'
import Chart from 'chart.js/auto'
import type { ApexOptions } from 'apexcharts'
import VueApexCharts from 'vue3-apexcharts'
import api from '@/services/api'

interface DashboardStats {
  totalProducts: number | null
  totalInventory: number | null
  totalOrders: number | null
  activeBranches: number | null
  lowStockCount: number | null
}

interface TopProduct {
  name: string
  sold: number
}

interface OrderPerDay {
  date: string
  count: number
}

interface LowStockProduct {
  sku_id: number
  sku_code: string
  product_name: string
  branch_id: number
  branch_name: string
  available: number
}

interface AnalyticsSummary {
  totalSales: number | null
  totalCogs: number | null
  totalProfit: number | null
  totalInventoryValue: number | null
  totalOrders: number | null
}

interface SalesPoint {
  day: string
  total: number
}

interface MonthlyPoint {
  month: string
  total: number
}

interface CategoryPoint {
  category: string
  total: number
}

interface BranchInventoryPoint {
  branch: string
  total: number
}

const isLoading = ref(false)
const isChartsLoading = ref(false)
const error = ref<string | null>(null)
const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: Chart<'bar'> | null = null
let refreshIntervalId: number | null = null
let themeObserver: MutationObserver | null = null

const stats = ref<DashboardStats>({
  totalProducts: null,
  totalInventory: null,
  totalOrders: null,
  activeBranches: null,
  lowStockCount: null,
})

const topProducts = ref<TopProduct[]>([])
const ordersPerDay = ref<OrderPerDay[]>([])
const lowStockProducts = ref<LowStockProduct[]>([])
const isDarkTheme = ref(document.documentElement.classList.contains('dark'))

const analytics = ref<AnalyticsSummary>({
  totalSales: null,
  totalCogs: null,
  totalProfit: null,
  totalInventoryValue: null,
  totalOrders: null,
})

const salesTrend = ref<SalesPoint[]>([])
const monthlySales = ref<MonthlyPoint[]>([])
const categorySales = ref<CategoryPoint[]>([])
const topSellingChart = ref<TopProduct[]>([])
const branchInventoryValue = ref<BranchInventoryPoint[]>([])

const maxProductSold = computed(() => {
  if (topProducts.value.length === 0) return 1
  return Math.max(...topProducts.value.map(p => p.sold), 1)
})

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const statText = (value: number | null) => {
  if (isLoading.value) return '…'
  if (value === null) return '—'
  return value.toLocaleString()
}

const moneyFormatter = new Intl.NumberFormat('en-PK', {
  style: 'currency',
  currency: 'PKR',
  currencyDisplay: 'narrowSymbol',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

const formatMoney = (value: number) => {
  // Normalize non-breaking spaces so values consistently render like "Rs 4,140,400".
  return moneyFormatter.format(value).replace(/[\u00A0\u202F]/g, ' ')
}

const statCurrency = (value: number | null) => {
  if (isLoading.value) return '…'
  if (value === null) return '—'
  return formatMoney(value)
}

const formatDateShort = (dateStr: string): string => {
  const date = new Date(dateStr + 'T00:00:00')
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const chartTheme = computed(() => {
  const dark = isDarkTheme.value
  return {
    mode: dark ? 'dark' : 'light',
    textColor: dark ? '#D1D5DB' : '#4B5563',
    gridColor: dark ? '#374151' : '#E5E7EB',
    tooltipTheme: dark ? 'dark' : 'light',
  }
})

const currencyFormatter = (value: number) => formatMoney(value)

const salesTrendSeries = computed(() => [
  {
    name: 'Sales',
    data: salesTrend.value.map((row) => Number(row.total || 0)),
  },
])

const salesTrendOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'line',
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  stroke: { width: 3, curve: 'smooth' },
  colors: ['#10B981'],
  xaxis: {
    categories: salesTrend.value.map((row) => formatDateShort(row.day)),
    labels: { style: { colors: chartTheme.value.textColor } },
  },
  yaxis: {
    labels: {
      style: { colors: chartTheme.value.textColor },
      formatter: (val) => currencyFormatter(Number(val)),
    },
  },
  grid: { borderColor: chartTheme.value.gridColor },
  tooltip: {
    theme: 'dark',
    style: { fontSize: '13px' },
    y: { formatter: (val) => currencyFormatter(Number(val)) },
  },
  dataLabels: { enabled: false },
  noData: { text: 'No sales trend data' },
}))

const monthlySalesSeries = computed(() => [
  {
    name: 'Monthly Sales',
    data: monthlySales.value.map((row) => Number(row.total || 0)),
  },
])

const monthlySalesOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
  },
  colors: ['#3B82F6'],
  plotOptions: {
    bar: {
      borderRadius: 6,
      columnWidth: '50%',
    },
  },
  xaxis: {
    categories: monthlySales.value.map((row) => row.month),
    labels: { style: { colors: chartTheme.value.textColor } },
  },
  yaxis: {
    labels: {
      style: { colors: chartTheme.value.textColor },
      formatter: (val) => currencyFormatter(Number(val)),
    },
  },
  grid: { borderColor: chartTheme.value.gridColor },
  tooltip: {
    theme: 'dark',
    style: { fontSize: '13px' },
    y: { formatter: (val) => currencyFormatter(Number(val)) },
  },
  dataLabels: { enabled: false },
  noData: { text: 'No monthly sales data' },
}))

const categorySalesSeries = computed(() => categorySales.value.map((row) => Number(row.total || 0)))

const categorySalesOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'donut',
    toolbar: { show: false },
  },
  labels: categorySales.value.map((row) => row.category),
  legend: {
    labels: { colors: chartTheme.value.textColor },
  },
  tooltip: {
    theme: 'dark',
    style: { fontSize: '13px' },
    y: { formatter: (val) => currencyFormatter(Number(val)) },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${Number(val).toFixed(1)}%`,
  },
  noData: { text: 'No category sales data' },
}))

const topSellingSeries = computed(() => [
  {
    name: 'Quantity Sold',
    data: topSellingChart.value.map((row) => Number(row.sold || 0)),
  },
])

const topSellingOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 4,
      barHeight: '70%',
    },
  },
  colors: ['#F59E0B'],
  xaxis: {
    categories: topSellingChart.value.map((row) => row.name),
    labels: { style: { colors: chartTheme.value.textColor } },
  },
  yaxis: {
    labels: { style: { colors: chartTheme.value.textColor } },
  },
  grid: { borderColor: chartTheme.value.gridColor },
  tooltip: {
    theme: 'dark',
    style: { fontSize: '13px' },
    y: { formatter: (val) => Number(val).toLocaleString() },
  },
  dataLabels: { enabled: false },
  noData: { text: 'No top products data' },
}))

const branchInventorySeries = computed(() => [
  {
    name: 'Inventory Value',
    data: branchInventoryValue.value.map((row) => Number(row.total || 0)),
  },
])

const branchInventoryOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
  },
  colors: ['#6366F1'],
  plotOptions: {
    bar: {
      borderRadius: 6,
      columnWidth: '45%',
    },
  },
  xaxis: {
    categories: branchInventoryValue.value.map((row) => row.branch),
    labels: { style: { colors: chartTheme.value.textColor } },
  },
  yaxis: {
    labels: {
      style: { colors: chartTheme.value.textColor },
      formatter: (val) => currencyFormatter(Number(val)),
    },
  },
  grid: { borderColor: chartTheme.value.gridColor },
  tooltip: {
    theme: 'dark',
    style: { fontSize: '13px' },
    y: { formatter: (val) => currencyFormatter(Number(val)) },
  },
  dataLabels: { enabled: false },
  noData: { text: 'No inventory valuation data' },
}))

const initChart = async () => {
  // Wait for DOM to be ready
  await nextTick()

  if (!chartCanvas.value) return

  // Destroy existing chart if it exists
  if (chartInstance) {
    chartInstance.destroy()
  }

  // Prepare chart data - last 14 days only
  const last14Days = ordersPerDay.value.slice(-14)
  const labels = last14Days.map(d => formatDateShort(d.date))
  const data = last14Days.map(d => d.count)

  const ctx = chartCanvas.value.getContext('2d')
  if (!ctx) return

  const chartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        max: Math.max(...data, 5),
        ticks: {
          stepSize: 1,
          color: isDarkTheme.value ? '#9CA3AF' : '#6B7280',
        },
        grid: {
          color: isDarkTheme.value ? '#374151' : '#E5E7EB',
        },
      },
      x: {
        ticks: {
          color: isDarkTheme.value ? '#9CA3AF' : '#6B7280',
        },
        grid: {
          display: false,
        },
      },
    },
  }

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Orders',
          data,
          backgroundColor: '#3B82F6',
          borderColor: '#1E40AF',
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false,
        },
      ],
    },
    options: chartOptions,
  })
}

const loadDashboard = async () => {
  isLoading.value = true
  isChartsLoading.value = true
  error.value = null

  try {
    const dashboardSummaryRes = await api.get('/dashboard/summary', { params: { trend_days: 30 } })

    const summary = (dashboardSummaryRes.data?.data || {}) as Record<string, any>

    analytics.value = {
      totalSales: toNumber(summary.total_sales),
      totalCogs: toNumber(summary.total_cogs),
      totalProfit: toNumber(summary.total_profit),
      totalInventoryValue: toNumber(summary.total_inventory_value),
      totalOrders: toNumber(summary.total_orders),
    }

    stats.value = {
      totalProducts: toNumber(summary.total_products),
      totalInventory: toNumber(summary.total_inventory),
      totalOrders: toNumber(summary.total_orders),
      activeBranches: toNumber(summary.active_branches),
      lowStockCount: toNumber(summary.low_stock_count),
    }

    lowStockProducts.value = Array.isArray(summary.low_stock)
      ? summary.low_stock.map((row: any) => ({
        sku_id: toNumber(row.sku_id),
        sku_code: String(row.sku_code || 'UNKNOWN'),
        product_name: String(row.product_name || 'Unknown Product'),
        branch_id: toNumber(row.branch_id),
        branch_name: String(row.branch_name || 'Unknown Branch'),
        available: Math.max(0, toNumber(row.qty_available)),
      }))
      : []

    await new Promise((resolve) => window.setTimeout(resolve, 0))

    salesTrend.value = Array.isArray(summary.sales_trend)
      ? summary.sales_trend.map((row: any) => ({ day: String(row.day || ''), total: toNumber(row.total) }))
      : []

    monthlySales.value = Array.isArray(summary.monthly_sales_chart)
      ? summary.monthly_sales_chart.map((row: any) => ({ month: String(row.month || ''), total: toNumber(row.total) }))
      : []

    categorySales.value = Array.isArray(summary.category_sales)
      ? summary.category_sales.map((row: any) => ({ category: String(row.category || 'Uncategorized'), total: toNumber(row.total) }))
      : []

    topSellingChart.value = Array.isArray(summary.top_selling_products)
      ? summary.top_selling_products.map((row: any) => ({ name: String(row.product_name || 'Unknown Product'), sold: toNumber(row.qty_sold) }))
      : []

    topProducts.value = topSellingChart.value.map((row) => ({
      name: row.name,
      sold: row.sold,
    }))

    if (Array.isArray(summary.orders_trend)) {
      ordersPerDay.value = summary.orders_trend.map((row: any) => ({
        date: String(row.day || ''),
        count: toNumber(row.count),
      }))
    } else {
      ordersPerDay.value = []
    }

    if (Array.isArray(summary.branch_inventory_value)) {
      const grouped = new Map<string, number>()

      summary.branch_inventory_value.forEach((row: any) => {
        const branchName = String(row.branch_name || `Branch ${toNumber(row.branch_id)}`)
        const current = grouped.get(branchName) || 0
        grouped.set(branchName, current + toNumber(row.total_value))
      })

      branchInventoryValue.value = Array.from(grouped.entries())
        .map(([branch, total]) => ({ branch, total }))
        .sort((a, b) => b.total - a.total)
    } else {
      branchInventoryValue.value = []
    }

    // Show chart canvases first, then initialize chart after canvas is mounted.
    isChartsLoading.value = false
    await initChart()
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load dashboard data.'
  } finally {
    isChartsLoading.value = false
    isLoading.value = false
  }
}

// Watch for dark mode changes and reinitialize chart
watch(
  () => isDarkTheme.value,
  () => {
    if (ordersPerDay.value.length > 0) {
      initChart()
    }
  }
)

onMounted(() => {
  themeObserver = new MutationObserver(() => {
    isDarkTheme.value = document.documentElement.classList.contains('dark')
  })

  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })

  loadDashboard()

  refreshIntervalId = window.setInterval(() => {
    loadDashboard()
  }, 60000)
})

onBeforeUnmount(() => {
  if (themeObserver) {
    themeObserver.disconnect()
    themeObserver = null
  }

  if (refreshIntervalId !== null) {
    clearInterval(refreshIntervalId)
    refreshIntervalId = null
  }

  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
})
</script>

<style>
.apexcharts-tooltip {
  background: #1f2937 !important;
  color: #ffffff !important;
  border: 1px solid #374151 !important;
}

.apexcharts-tooltip-title {
  background: #111827 !important;
  color: #ffffff !important;
  border-bottom: 1px solid #374151 !important;
}

.apexcharts-tooltip * {
  color: #ffffff !important;
}
</style>
