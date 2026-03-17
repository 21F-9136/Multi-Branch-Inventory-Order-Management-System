<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Branch Performance Overview</h2>
      <div class="mt-3">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
          Branch: {{ branchName }}
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-emerald-500">
        <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Today Sales</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(metrics.todaySales) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-indigo-500">
        <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Monthly Sales</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(metrics.monthlySales) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-cyan-500">
        <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Orders</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statText(metrics.totalOrders) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-amber-500">
        <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Inventory Value</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statCurrency(metrics.inventoryValue) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-red-500">
        <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Low Stock Products</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ statText(metrics.lowStockCount) }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sales Trend (Last 30 Days)</h3>
        <VueApexCharts type="line" height="320" :options="salesTrendOptions" :series="salesTrendSeries" />
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Selling Products</h3>
        <VueApexCharts type="bar" height="320" :options="topProductsOptions" :series="topProductsSeries" />
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Low Stock Inventory</h3>

      <div v-if="isLoading" class="text-sm text-gray-500 dark:text-gray-400">Loading manager dashboard...</div>
      <div v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</div>
      <div v-else-if="lowStockItems.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
        Great news, no low stock items in your branch.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
              <th class="text-left px-4 py-2 font-semibold text-gray-900 dark:text-white">Product</th>
              <th class="text-left px-4 py-2 font-semibold text-gray-900 dark:text-white">SKU</th>
              <th class="text-right px-4 py-2 font-semibold text-gray-900 dark:text-white">Available</th>
              <th class="text-right px-4 py-2 font-semibold text-gray-900 dark:text-white">Reorder Level</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in lowStockItems"
              :key="item.sku_id"
              class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50"
            >
              <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ item.product_name || 'Unknown Product' }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ item.sku_code || '—' }}</td>
              <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ item.available_quantity }}</td>
              <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ item.reorder_level }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { ApexOptions } from 'apexcharts'
import VueApexCharts from 'vue3-apexcharts'
import api from '@/services/api'

interface Metrics {
  todaySales: number | null
  monthlySales: number | null
  totalOrders: number | null
  inventoryValue: number | null
  lowStockCount: number | null
}

interface SalesTrendPoint {
  day: string
  total: number
}

interface TopSellingItem {
  sku_id: number
  sku_code: string | null
  product_name: string | null
  qty_sold: number
}

interface LowStockItem {
  sku_id: number
  sku_code: string | null
  product_name: string | null
  available_quantity: number
  reorder_level: number
}

const isLoading = ref(false)
const error = ref<string | null>(null)

const branchName = ref('Assigned Branch')

const metrics = ref<Metrics>({
  todaySales: null,
  monthlySales: null,
  totalOrders: null,
  inventoryValue: null,
  lowStockCount: null,
})

const salesTrend = ref<SalesTrendPoint[]>([])
const topSellingProducts = ref<TopSellingItem[]>([])
const lowStockItems = ref<LowStockItem[]>([])

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const moneyFormatter = new Intl.NumberFormat('en-PK', {
  style: 'currency',
  currency: 'PKR',
  currencyDisplay: 'narrowSymbol',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

const formatMoney = (value: number) => moneyFormatter.format(value).replace(/[\u00A0\u202F]/g, ' ')

const statCurrency = (value: number | null) => {
  if (isLoading.value) return '…'
  if (value === null) return '—'
  return formatMoney(value)
}

const statText = (value: number | null) => {
  if (isLoading.value) return '…'
  if (value === null) return '—'
  return value.toLocaleString()
}

const salesTrendSeries = computed(() => [
  {
    name: 'Sales',
    data: salesTrend.value.map((row) => row.total),
  },
])

const salesTrendOptions = computed<ApexOptions>(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  stroke: { curve: 'smooth', width: 3 },
  colors: ['#0ea5e9'],
  xaxis: {
    categories: salesTrend.value.map((row) => row.day),
    labels: { rotate: -45 },
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (value: number) => formatMoney(value),
    },
  },
  dataLabels: { enabled: false },
  grid: { borderColor: '#33415522' },
}))

const topProductsSeries = computed(() => [
  {
    name: 'Units Sold',
    data: topSellingProducts.value.map((row) => row.qty_sold),
  },
])

const topProductsOptions = computed<ApexOptions>(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  colors: ['#f59e0b'],
  plotOptions: { bar: { borderRadius: 6, horizontal: true } },
  xaxis: {
    categories: topSellingProducts.value.map((row) => row.product_name || row.sku_code || `SKU #${row.sku_id}`),
  },
  tooltip: {
    theme: 'dark',
  },
  dataLabels: { enabled: false },
  grid: { borderColor: '#33415522' },
}))

const loadDashboard = async () => {
  isLoading.value = true
  error.value = null

  try {
    const response = await api.get('/manager/dashboard/summary', { params: { trend_days: 30 } })
    const summary = response.data?.data || {}

    branchName.value = String(summary.branch_name || 'Assigned Branch')

    metrics.value = {
      todaySales: toNumber(summary.today_sales),
      monthlySales: toNumber(summary.monthly_sales),
      totalOrders: toNumber(summary.total_orders),
      inventoryValue: toNumber(summary.inventory_value),
      lowStockCount: toNumber(summary.low_stock_count),
    }

    salesTrend.value = Array.isArray(summary.sales_trend)
      ? summary.sales_trend.map((row: any) => ({ day: String(row.day || ''), total: toNumber(row.total) }))
      : []

    topSellingProducts.value = Array.isArray(summary.top_selling_products)
      ? summary.top_selling_products.map((row: any) => ({
          sku_id: toNumber(row.sku_id),
          sku_code: row.sku_code ?? null,
          product_name: row.product_name ?? null,
          qty_sold: toNumber(row.qty_sold),
        }))
      : []

    lowStockItems.value = Array.isArray(summary.low_stock_items)
      ? summary.low_stock_items.map((row: any) => ({
          sku_id: toNumber(row.sku_id),
          sku_code: row.sku_code ?? null,
          product_name: row.product_name ?? null,
          available_quantity: toNumber(row.available_quantity),
          reorder_level: toNumber(row.reorder_level),
        }))
      : []
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load manager dashboard.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>
