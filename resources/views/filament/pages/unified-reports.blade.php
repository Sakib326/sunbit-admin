{{-- filepath: resources/views/filament/pages/unified-reports.blade.php --}}

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Report Filters</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Customize your report by applying filters below
                    </p>
                </div>
                <button wire:click="resetFilters"
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                    Reset Filters
                </button>
            </div>

            {{-- Unified Filter Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                {{-- Date Range (Always present) --}}
                <div class="xl:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        📅 Date Range
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" wire:model.live="dateFrom"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <input type="date" wire:model.live="dateTo"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                </div>

                {{-- Package Filter (Tours & Car Rentals) --}}
                @if (in_array($this->getReportType(), ['tours', 'car_rental']))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ $this->getReportType() === 'tours' ? '🏞️ Tour Package' : '🚗 Car Model' }}
                        </label>
                        <select wire:model.live="packageId"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All {{ $this->getReportType() === 'tours' ? 'Packages' : 'Models' }}
                            </option>
                            @foreach ($this->getFilterOptions()['packages'] as $id => $title)
                                <option value="{{ $id }}">{{ Str::limit($title, 30) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            👤 Agent
                        </label>
                        <select wire:model.live="agentId"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Agents</option>
                            @foreach ($this->getFilterOptions()['agents'] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            📊 Booking Status
                        </label>
                        <select wire:model.live="status"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Status</option>
                            @foreach ($this->getFilterOptions()['statuses'] as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            💳 Payment Status
                        </label>
                        <select wire:model.live="paymentStatus"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Status</option>
                            @foreach ($this->getFilterOptions()['paymentStatuses'] as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Car Brand (Car Rentals only) --}}
                    @if ($this->getReportType() === 'car_rental')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                🏷️ Car Brand
                            </label>
                            <select wire:model.live="carBrand"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <option value="">All Brands</option>
                                @foreach ($this->getFilterOptions()['carBrands'] as $brand => $label)
                                    <option value="{{ $brand }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif

                {{-- Financial Report Filters --}}
                @if ($this->getReportType() === 'financial')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            🏢 Service Type
                        </label>
                        <select wire:model.live="serviceType"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Services</option>
                            @foreach ($this->getFilterOptions()['serviceTypes'] as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            💳 Payment Method
                        </label>
                        <select wire:model.live="paymentMethod"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Methods</option>
                            @foreach ($this->getFilterOptions()['paymentMethods'] as $method => $label)
                                <option value="{{ $method }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            📊 Payment Status
                        </label>
                        <select wire:model.live="status"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Status</option>
                            @foreach ($this->getFilterOptions()['statuses'] as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            🏷️ Payment Type
                        </label>
                        <select wire:model.live="paymentType"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Types</option>
                            @foreach ($this->getFilterOptions()['paymentTypes'] as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Active Filters Display --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($dateFrom || $dateTo)
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                        📅 {{ $dateFrom ?? 'Start' }} to {{ $dateTo ?? 'End' }}
                        <button wire:click="$set('dateFrom', null); $set('dateTo', null)"
                            class="ml-1 hover:text-blue-600 font-bold">×</button>
                    </span>
                @endif

                @if (isset($packageId) && $packageId)
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                        {{ $this->getReportType() === 'tours' ? '🏞️' : '🚗' }} Package Selected
                        <button wire:click="$set('packageId', null)"
                            class="ml-1 hover:text-green-600 font-bold">×</button>
                    </span>
                @endif

                @if (isset($agentId) && $agentId)
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 bg-purple-100 text-purple-800 text-sm rounded-full">
                        👤 Agent Selected
                        <button wire:click="$set('agentId', null)"
                            class="ml-1 hover:text-purple-600 font-bold">×</button>
                    </span>
                @endif

                @if (isset($status) && $status)
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 bg-orange-100 text-orange-800 text-sm rounded-full">
                        📊 Status: {{ ucfirst($status) }}
                        <button wire:click="$set('status', null)"
                            class="ml-1 hover:text-orange-600 font-bold">×</button>
                    </span>
                @endif

                @if (isset($serviceType) && $serviceType)
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-800 text-sm rounded-full">
                        🏢 Service: {{ $serviceType }}
                        <button wire:click="$set('serviceType', null)"
                            class="ml-1 hover:text-indigo-600 font-bold">×</button>
                    </span>
                @endif

                @if (isset($carBrand) && $carBrand)
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded-full">
                        🏷️ Brand: {{ $carBrand }}
                        <button wire:click="$set('carBrand', null)"
                            class="ml-1 hover:text-yellow-600 font-bold">×</button>
                    </span>
                @endif
            </div>
        </div>

        {{-- Stats Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($this->getStats() as $stat)
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-all duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                </path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $stat->getLabel() }}
                            </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stat->getValue() }}</p>
                            @if ($stat->getDescription())
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $stat->getDescription() }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Data Table --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{ $this->table }}
        </div>
    </div>

    {{-- Loading State --}}
    <div wire:loading.delay class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center gap-3 shadow-xl">
            <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                    stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <span class="text-gray-900 font-medium">🔄 Loading report data...</span>
        </div>
    </div>
</x-filament-panels::page>
