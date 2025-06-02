<?php

// filepath: app/Filament/Resources/BookingResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use App\Models\User;
use App\Models\TourPackage;
use App\Models\CarRentalPackage;
use App\Models\AgentCommission;
use App\Models\TourPackageBookingLimit;
use App\Models\CarRentalBookingLimit;
use App\Models\Upazilla;
use App\Filament\Resources\PaymentResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Booking Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'booking_reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // Step 1: Service Type Selection
                    Forms\Components\Wizard\Step::make('Service Type')
                        ->schema([
                            Forms\Components\Select::make('service_type')
                                ->label('Service Type')
                                ->options([
                                    'TOURS' => 'Tours',
                                    'CAR_RENTAL' => 'Car Rental',
                                    'FLIGHT' => 'Flight',
                                    'HOTEL' => 'Hotel',
                                    'TRANSFER' => 'Transfer',
                                    'CRUISE' => 'Cruise',
                                    'TRANSPORT' => 'Transport',
                                    'VISA' => 'Visa'
                                ])
                                ->default('TOURS')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    // Reset form when service type changes
                                    $set('tour_package_id', null);
                                    $set('car_rental_package_id', null);
                                    $set('original_price', null);
                                    $set('selling_price', null);
                                    $set('final_amount', null);
                                }),

                            Forms\Components\Select::make('booking_source')
                                ->options([
                                    'admin_pos' => 'Admin POS',
                                    'agent' => 'Agent Portal',
                                    'website' => 'Website',
                                    'mobile_app' => 'Mobile App'
                                ])
                                ->default('admin_pos')
                                ->required(),

                            Forms\Components\TextInput::make('booking_reference')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Auto-generated on save'),
                        ])
                        ->columns(1),

                    // Step 2a: Tour Package Selection (Only for TOURS)
                    Forms\Components\Wizard\Step::make('Tour Package')
                        ->schema([
                            Forms\Components\Select::make('tour_package_id')
                                ->label('Tour Package')
                                ->relationship('tourPackage', 'title', fn (Builder $query) => $query->where('status', 'active'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    if ($state) {
                                        $package = TourPackage::find($state);
                                        if ($package) {
                                            // Auto-set pricing
                                            $set('original_price', $package->base_price_adult);

                                            // Auto-calculate end date
                                            if ($get('service_date')) {
                                                $endDate = Carbon::parse($get('service_date'))
                                                    ->addDays($package->number_of_days - 1);
                                                $set('service_end_date', $endDate);
                                            }

                                            // Calculate pricing and check availability
                                            static::calculateCommissionAndPricing($get, $set);
                                            static::calculateTotalPricing($get, $set);
                                            static::checkAvailability($get, $set);
                                        }
                                    }
                                }),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('adults')
                                        ->label('Adults')
                                        ->required()
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->maxValue(6)
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                            static::calculateTotalPricing($get, $set);
                                            static::checkAvailability($get, $set);
                                        }),

                                    Forms\Components\TextInput::make('children')
                                        ->label('Children')
                                        ->required()
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(6)
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                            static::calculateTotalPricing($get, $set);
                                            static::checkAvailability($get, $set);
                                        }),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\DatePicker::make('service_date')
                                        ->label('Tour Start Date')
                                        ->required()
                                        ->minDate(today())
                                        ->live()
                                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                            if ($state && $get('tour_package_id')) {
                                                // Auto-calculate end date
                                                $package = TourPackage::find($get('tour_package_id'));
                                                if ($package) {
                                                    $endDate = Carbon::parse($state)->addDays($package->number_of_days - 1);
                                                    $set('service_end_date', $endDate);
                                                }
                                                static::checkAvailability($get, $set);
                                            }
                                        }),

                                    Forms\Components\DatePicker::make('service_end_date')
                                        ->label('Tour End Date')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            // Availability Check Display
                            Forms\Components\Placeholder::make('availability_status')
                                ->label('Availability Status')
                                ->content(function (Forms\Get $get) {
                                    return static::getAvailabilityStatus($get);
                                })
                                ->visible(fn (Forms\Get $get) => $get('tour_package_id') && $get('service_date')),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('service_type') === 'TOURS'),

                    // Step 2b: Car Rental Package Selection (Only for CAR_RENTAL)
                    Forms\Components\Wizard\Step::make('Car Rental Package')
                        ->schema([
                            Forms\Components\Select::make('car_rental_package_id')
                                ->label('Car Rental Package')
                                ->relationship('carRentalPackage', 'title', fn (Builder $query) => $query->where('status', 'active'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    if ($state) {
                                        $package = CarRentalPackage::find($state);
                                        if ($package) {
                                            // Auto-set pricing
                                            $set('original_price', $package->daily_price);

                                            // Calculate pricing and check availability
                                            static::calculateCarRentalCommissionAndPricing($get, $set);
                                            static::calculateCarRentalTotalPricing($get, $set);
                                            static::checkCarRentalAvailability($get, $set);
                                        }
                                    }
                                }),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\DatePicker::make('service_date')
                                        ->label('Pickup Date')
                                        ->required()
                                        ->minDate(today())
                                        ->live()
                                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                            if ($state && $get('service_end_date')) {
                                                static::calculateCarRentalTotalPricing($get, $set);
                                                static::checkCarRentalAvailability($get, $set);
                                            }
                                        }),

                                    Forms\Components\DatePicker::make('service_end_date')
                                        ->label('Return Date')
                                        ->required()
                                        ->minDate(fn (Forms\Get $get) => $get('service_date') ?: today())
                                        ->live()
                                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                            if ($state && $get('service_date')) {
                                                static::calculateCarRentalTotalPricing($get, $set);
                                                static::checkCarRentalAvailability($get, $set);
                                            }
                                        }),
                                ]),

                            // Car Rental Availability Check
                            Forms\Components\Placeholder::make('car_availability_status')
                                ->label('Car Availability Status')
                                ->content(function (Forms\Get $get) {
                                    return static::getCarRentalAvailabilityStatus($get);
                                })
                                ->visible(fn (Forms\Get $get) => $get('car_rental_package_id') && $get('service_date') && $get('service_end_date')),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('service_type') === 'CAR_RENTAL'),

                    // Step 3: Customer Information
                    Forms\Components\Wizard\Step::make('Customer')
                        ->schema([
                            Forms\Components\Select::make('customer_id')
                                ->label('Select Customer')
                                ->relationship('customer', 'name', fn (Builder $query) => $query->where('role', 'customer'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $customer = User::find($state);
                                        if ($customer) {
                                            $set('customer_name', $customer->name);
                                            $set('customer_email', $customer->email);
                                            $set('customer_phone', $customer->phone ?? '');
                                        }
                                    }
                                })
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Full Name')
                                        ->required()
                                        ->placeholder('Enter full name'),
                                    Forms\Components\TextInput::make('email')
                                        ->label('Email Address')
                                        ->email()
                                        ->required()
                                        ->unique('users', 'email')
                                        ->placeholder('customer@email.com'),
                                    Forms\Components\TextInput::make('phone')
                                        ->label('Phone Number')
                                        ->tel()
                                        ->placeholder('+1234567890'),
                                    Forms\Components\TextInput::make('password')
                                        ->label('Password')
                                        ->password()
                                        ->required()
                                        ->default('password123')
                                        ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
                                    Forms\Components\Hidden::make('role')->default('customer'),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('customer_name')
                                        ->label('Customer Name')
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\TextInput::make('customer_email')
                                        ->label('Customer Email')
                                        ->email()
                                        ->required()
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('customer_phone')
                                        ->label('Customer Phone')
                                        ->tel()
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\TextInput::make('customer_passport_number')
                                        ->label('Passport Number')
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Select::make('agent_id')
                                ->label('Select Agent (Optional)')
                                ->relationship('agent', 'name', fn (Builder $query) => $query->where('role', 'agent'))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $serviceType = $get('service_type');
                                    if ($serviceType === 'TOURS' && $get('tour_package_id')) {
                                        static::calculateCommissionAndPricing($get, $set);
                                        static::calculateTotalPricing($get, $set);
                                    } elseif ($serviceType === 'CAR_RENTAL' && $get('car_rental_package_id')) {
                                        static::calculateCarRentalCommissionAndPricing($get, $set);
                                        static::calculateCarRentalTotalPricing($get, $set);
                                    }
                                }),

                            Forms\Components\Textarea::make('special_requirements')
                                ->label('Special Requirements')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),

                    // Step 4a: Tour Details (Only for TOURS)
                    Forms\Components\Wizard\Step::make('Tour Details')
                        ->schema([
                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('pickup_location')
                                        ->label('Pickup Location')
                                        ->maxLength(255)
                                        ->placeholder('Enter pickup location'),

                                    Forms\Components\TimePicker::make('pickup_time')
                                        ->label('Pickup Time')
                                        ->default('08:00'),

                                    Forms\Components\TextInput::make('drop_location')
                                        ->label('Drop Location')
                                        ->maxLength(255)
                                        ->placeholder('Enter drop location'),
                                ]),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\Select::make('room_type')
                                        ->label('Room Type')
                                        ->options([
                                            'single' => 'Single Room',
                                            'twin' => 'Twin Sharing',
                                            'triple' => 'Triple Sharing',
                                            'family' => 'Family Room'
                                        ])
                                        ->default('twin'),

                                    Forms\Components\Select::make('meal_plan')
                                        ->label('Meal Plan')
                                        ->options([
                                            'no_meals' => 'No Meals',
                                            'breakfast' => 'Breakfast Only',
                                            'half_board' => 'Half Board (Breakfast + Dinner)',
                                            'full_board' => 'Full Board (All Meals)'
                                        ])
                                        ->default('breakfast'),

                                    Forms\Components\TextInput::make('guide_language')
                                        ->label('Guide Language')
                                        ->default('English')
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('emergency_contact')
                                        ->label('Emergency Contact')
                                        ->tel()
                                        ->maxLength(255),

                                    Forms\Components\Textarea::make('tour_notes')
                                        ->label('Tour Notes')
                                        ->rows(2),
                                ]),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('service_type') === 'TOURS'),

                    // Step 4b: Car Rental Details (Only for CAR_RENTAL)
                    Forms\Components\Wizard\Step::make('Car Rental Details')
                            ->schema([
                                Forms\Components\Placeholder::make('car_rental_info')
                                    ->label('Car Rental Information')
                                    ->content(function (Forms\Get $get) {
                                        $packageId = $get('car_rental_package_id');
                                        if (!$packageId) {
                                            return 'Select a car rental package to see details';
                                        }

                                        $package = CarRentalPackage::find($packageId);
                                        if (!$package) {
                                            return 'Car package not found';
                                        }

                                        return "
                                            <div class='p-4 bg-blue-50 rounded-lg'>
                                                <h4 class='font-semibold text-blue-900'>{$package->getFullCarName()}</h4>
                                                <p class='text-blue-700 mt-1'>Pickup and driver details will be arranged separately after booking confirmation.</p>
                                                <p class='text-sm text-blue-600 mt-2'>Available locations: " . $package->locations->count() . " areas</p>
                                            </div>
                                        ";
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('special_requirements')
                                    ->label('Special Requirements (Optional)')
                                    ->rows(2)
                                    ->placeholder('Any special requests for the car rental...')
                                    ->helperText('Pickup location, driver preferences, etc.')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('service_type') === 'CAR_RENTAL'),

                    // Step 5: Pricing & Payment (Universal)
                    Forms\Components\Wizard\Step::make('Pricing & Payment')
                        ->schema([
                            // Pricing Details
                            Forms\Components\Section::make('Pricing Details')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('original_price')
                                                ->label(fn (Forms\Get $get) => $get('service_type') === 'CAR_RENTAL' ? 'Daily Price' : 'Original Price (Per Person)')
                                                ->required()
                                                ->numeric()
                                                ->prefix('৳')
                                                ->disabled()
                                                ->dehydrated()
                                                ->helperText(fn (Forms\Get $get) => $get('service_type') === 'CAR_RENTAL' ? 'Daily rental price' : 'Base price from tour package'),

                                            Forms\Components\TextInput::make('agent_discount_percent')
                                                ->label('Agent Commission %')
                                                ->numeric()
                                                ->suffix('%')
                                                ->disabled()
                                                ->dehydrated()
                                                ->helperText('Auto-calculated commission'),

                                            Forms\Components\TextInput::make('agent_cost_price')
                                                ->label(fn (Forms\Get $get) => $get('service_type') === 'CAR_RENTAL' ? 'Agent Daily Price' : 'Agent Cost Price (Per Person)')
                                                ->numeric()
                                                ->prefix('৳')
                                                ->disabled()
                                                ->dehydrated()
                                                ->helperText('Price after agent commission'),
                                        ]),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('selling_price')
                                                ->label('Total Selling Price')
                                                ->required()
                                                ->numeric()
                                                ->prefix('৳')
                                                ->disabled()
                                                ->dehydrated()
                                                ->helperText(fn (Forms\Get $get) => $get('service_type') === 'CAR_RENTAL' ? 'Total rental price for all days' : 'Auto-calculated based on passengers and agent pricing'),

                                            Forms\Components\TextInput::make('final_amount')
                                                ->label('Final Amount')
                                                ->required()
                                                ->numeric()
                                                ->prefix('৳')
                                                ->disabled()
                                                ->dehydrated()
                                                ->helperText('Total amount customer pays'),
                                        ]),

                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('additional_charges')
                                                ->label('Additional Charges')
                                                ->numeric()
                                                ->default(0)
                                                ->prefix('৳')
                                                ->live(debounce: 1000)
                                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                    static::calculateFinalAmount($get, $set);
                                                }),

                                            Forms\Components\TextInput::make('discount_amount')
                                                ->label('Discount Amount')
                                                ->numeric()
                                                ->default(0)
                                                ->prefix('৳')
                                                ->live(debounce: 1000)
                                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                    static::calculateFinalAmount($get, $set);
                                                }),

                                            Forms\Components\TextInput::make('due_amount')
                                                ->label('Due Amount')
                                                ->numeric()
                                                ->prefix('৳')
                                                ->disabled()
                                                ->dehydrated()
                                                ->default(0),
                                        ]),
                                ]),

                            // Payment Options
                            Forms\Components\Section::make('Payment Options')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Toggle::make('allow_partial_payment')
                                                ->label('Allow Partial Payment')
                                                ->default(false)
                                                ->live(),

                                            Forms\Components\TextInput::make('minimum_payment_amount')
                                                ->label('Minimum Payment Amount')
                                                ->numeric()
                                                ->prefix('৳')
                                                ->visible(fn (Forms\Get $get) => $get('allow_partial_payment'))
                                                ->requiredWith('allow_partial_payment'),
                                        ]),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Select::make('payment_status')
                                                ->label('Payment Status')
                                                ->options([
                                                    'pending' => 'Pending',
                                                    'zero_payment' => 'Zero Payment'
                                                ])
                                                ->default('pending')
                                                ->required()
                                                ->helperText('Payment status will be updated when payments are made'),

                                            Forms\Components\Select::make('status')
                                                ->label('Booking Status')
                                                ->options([
                                                    'draft' => 'Draft',
                                                    'confirmed' => 'Confirmed',
                                                ])
                                                ->default('confirmed')
                                                ->required(),
                                        ]),
                                ]),

                            // Admin Options
                            Forms\Components\Section::make('Admin Options')
                                ->schema([
                                    Forms\Components\Toggle::make('admin_override_payment')
                                        ->label('Admin Override Payment')
                                        ->default(false),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Textarea::make('payment_notes')
                                                ->label('Payment Notes')
                                                ->rows(2),

                                            Forms\Components\Textarea::make('internal_notes')
                                                ->label('Internal Notes')
                                                ->rows(2),
                                        ]),

                                    Forms\Components\Hidden::make('booked_by')
                                        ->default(fn () => auth()->id()),

                                    Forms\Components\Hidden::make('paid_amount')
                                        ->default(0),
                                ])
                                ->collapsible()
                                ->collapsed(),
                        ]),
                ])
                ->columnSpanFull()
                ->skippable(),
            ])
            ->columns(1);
    }

    // ====================== TOUR AVAILABILITY METHODS ======================
    protected static function getAvailabilityStatus($get)
    {
        $tourPackageId = $get('tour_package_id');
        $serviceDate = $get('service_date');
        $adults = intval($get('adults') ?? 0);
        $children = intval($get('children') ?? 0);
        $totalPax = $adults + $children;

        if (!$tourPackageId || !$serviceDate || $totalPax <= 0) {
            return new \Illuminate\Support\HtmlString('
                <div class="p-4 bg-gray-100 rounded-lg text-center">
                    <span class="text-gray-500">Select tour package and date to check availability</span>
                </div>
            ');
        }

        $package = TourPackage::find($tourPackageId);
        if (!$package) {
            return new \Illuminate\Support\HtmlString('
                <div class="p-4 bg-red-100 rounded-lg text-center border border-red-200">
                    <span class="text-red-600 font-medium">⚠️ Invalid tour package selected</span>
                </div>
            ');
        }

        // Check availability
        $overrideLimit = TourPackageBookingLimit::where('tour_package_id', $tourPackageId)
            ->where('date', $serviceDate)
            ->first();

        $maxBooking = $overrideLimit ? $overrideLimit->max_booking : $package->max_booking_per_day;

        // Check existing bookings - Handle missing column gracefully
        try {
            $existingBookings = Booking::where('service_type', 'TOURS')
                ->where('tour_package_id', $tourPackageId)
                ->where('service_date', $serviceDate)
                ->whereNotIn('status', ['cancelled'])
                ->sum(\DB::raw('adults + children'));
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle case where tour_package_id column doesn't exist yet
            if (str_contains($e->getMessage(), 'tour_package_id')) {
                // Fallback: Use tour details relationship
                $existingBookings = Booking::where('service_type', 'TOURS')
                    ->whereHas('tourDetails', function ($query) use ($tourPackageId) {
                        $query->where('tour_package_id', $tourPackageId);
                    })
                    ->where('service_date', $serviceDate)
                    ->whereNotIn('status', ['cancelled'])
                    ->sum(\DB::raw('adults + children'));
            } else {
                $existingBookings = 0;
            }
        }

        $availableSlots = $maxBooking - $existingBookings;
        $canBook = $totalPax <= $availableSlots;

        $statusColor = $canBook ? 'green' : 'red';
        $statusIcon = $canBook ? '✅' : '❌';
        $statusText = $canBook ? 'Available' : 'Not Available';

        return new \Illuminate\Support\HtmlString("
            <div class='p-4 bg-{$statusColor}-100 rounded-lg border border-{$statusColor}-200'>
                <div class='flex items-center justify-between'>
                    <div>
                        <span class='text-{$statusColor}-700 font-medium'>{$statusIcon} {$statusText}</span>
                        <div class='text-sm text-{$statusColor}-600 mt-1'>
                            Requesting: {$totalPax} passengers | Available: {$availableSlots} slots
                        </div>
                    </div>
                    <div class='text-right text-sm text-{$statusColor}-600'>
                        <div>Max Daily: {$maxBooking}</div>
                        <div>Booked: {$existingBookings}</div>
                    </div>
                </div>
            </div>
        ");
    }

    // ====================== CAR RENTAL AVAILABILITY METHODS ======================
    protected static function getCarRentalAvailabilityStatus($get)
    {
        $packageId = $get('car_rental_package_id');
        $pickupDate = $get('service_date');
        $returnDate = $get('service_end_date');

        if (!$packageId || !$pickupDate || !$returnDate) {
            return new \Illuminate\Support\HtmlString('
                <div class="p-4 bg-gray-100 rounded-lg text-center">
                    <span class="text-gray-500">Select car package and dates to check availability</span>
                </div>
            ');
        }

        $package = CarRentalPackage::find($packageId);
        if (!$package) {
            return new \Illuminate\Support\HtmlString('
                <div class="p-4 bg-red-100 rounded-lg text-center border border-red-200">
                    <span class="text-red-600 font-medium">⚠️ Invalid car package selected</span>
                </div>
            ');
        }

        $pickupCarbon = Carbon::parse($pickupDate);
        $returnCarbon = Carbon::parse($returnDate);
        $rentalDays = $pickupCarbon->diffInDays($returnCarbon) ?: 1;

        // Check availability for each day in the rental period
        $minAvailable = $package->total_cars;
        $conflictDates = [];

        for ($date = $pickupCarbon->copy(); $date->lte($returnCarbon); $date->addDay()) {
            $availableCars = $package->getAvailableCarsForDate($date->format('Y-m-d'));
            if ($availableCars < 1) {
                $conflictDates[] = $date->format('d M Y');
            }
            $minAvailable = min($minAvailable, $availableCars);
        }

        $canBook = empty($conflictDates);
        $statusColor = $canBook ? 'green' : 'red';
        $statusIcon = $canBook ? '✅' : '❌';
        $statusText = $canBook ? 'Available' : 'Not Available';

        $conflictText = '';
        if (!empty($conflictDates)) {
            $conflictText = '<div class="text-sm text-red-600 mt-1">Conflicts on: ' . implode(', ', array_slice($conflictDates, 0, 3)) . (count($conflictDates) > 3 ? ' +' . (count($conflictDates) - 3) . ' more' : '') . '</div>';
        }

        return new \Illuminate\Support\HtmlString("
            <div class='p-4 bg-{$statusColor}-100 rounded-lg border border-{$statusColor}-200'>
                <div class='flex items-center justify-between'>
                    <div>
                        <span class='text-{$statusColor}-700 font-medium'>{$statusIcon} {$statusText}</span>
                        <div class='text-sm text-{$statusColor}-600 mt-1'>
                            Rental Period: {$rentalDays} days | Min Available: {$minAvailable} cars
                        </div>
                        {$conflictText}
                    </div>
                    <div class='text-right text-sm text-{$statusColor}-600'>
                        <div>Total Cars: {$package->total_cars}</div>
                        <div>Brand: {$package->car_brand}</div>
                    </div>
                </div>
            </div>
        ");
    }

    // ====================== TOUR CALCULATION METHODS ======================
    protected static function calculateCommissionAndPricing($get, $set)
    {
        $serviceType = $get('service_type') ?? 'TOURS';
        $agentId = $get('agent_id');
        $tourPackageId = $get('tour_package_id');

        $commissionPercent = 0;

        // Check agent-specific commission first
        if ($agentId) {
            $agentCommission = AgentCommission::where('agent_id', $agentId)
                ->where('service', $serviceType)
                ->first();

            if ($agentCommission) {
                $commissionPercent = $agentCommission->commission_percent;
            }
        }

        // Fallback to tour package commission for tours
        if ($commissionPercent == 0 && $tourPackageId && $serviceType === 'TOURS') {
            $tourPackage = TourPackage::find($tourPackageId);
            if ($tourPackage) {
                $commissionPercent = $tourPackage->agent_discount_percent ?? 0;
            }
        }

        $originalPrice = floatval($get('original_price')) ?: 0;
        $agentCostPrice = $originalPrice * (1 - ($commissionPercent / 100));

        $set('agent_discount_percent', $commissionPercent);
        $set('agent_cost_price', round($agentCostPrice, 2));

        static::calculateTotalPricing($get, $set);
    }

    protected static function calculateTotalPricing($get, $set)
    {
        $adults = intval($get('adults')) ?: 0;
        $children = intval($get('children')) ?: 0;
        $agentId = $get('agent_id');

        // Determine which price to use
        if ($agentId) {
            // Agent booking - use agent cost price
            $basePrice = floatval($get('agent_cost_price')) ?: 0;
        } else {
            // Direct booking - use original price
            $basePrice = floatval($get('original_price')) ?: 0;
        }

        // Calculate total (children get 30% discount)
        $adultTotal = $adults * $basePrice;
        $childTotal = $children * ($basePrice * 0.7);
        $totalPrice = $adultTotal + $childTotal;

        $set('selling_price', round($totalPrice, 2));

        static::calculateFinalAmount($get, $set);
    }

    // ====================== CAR RENTAL CALCULATION METHODS ======================
    protected static function calculateCarRentalCommissionAndPricing($get, $set)
    {
        $agentId = $get('agent_id');
        $packageId = $get('car_rental_package_id');

        $commissionPercent = 0;

        // Check agent-specific commission first
        if ($agentId) {
            $agentCommission = AgentCommission::where('agent_id', $agentId)
                ->where('service', 'CAR_RENTAL')
                ->first();

            if ($agentCommission) {
                $commissionPercent = $agentCommission->commission_percent;
            }
        }

        // Fallback to car package commission
        if ($commissionPercent == 0 && $packageId) {
            $carPackage = CarRentalPackage::find($packageId);
            if ($carPackage) {
                $commissionPercent = $carPackage->agent_commission_percent ?? 0;
            }
        }

        $originalPrice = floatval($get('original_price')) ?: 0;
        $agentCostPrice = $originalPrice * (1 - ($commissionPercent / 100));

        $set('agent_discount_percent', $commissionPercent);
        $set('agent_cost_price', round($agentCostPrice, 2));

        static::calculateCarRentalTotalPricing($get, $set);
    }

    protected static function calculateCarRentalTotalPricing($get, $set)
    {
        $pickupDate = $get('service_date');
        $returnDate = $get('service_end_date');
        $agentId = $get('agent_id');

        if (!$pickupDate || !$returnDate) {
            return;
        }

        $pickupCarbon = Carbon::parse($pickupDate);
        $returnCarbon = Carbon::parse($returnDate);
        $rentalDays = $pickupCarbon->diffInDays($returnCarbon) ?: 1;

        // Determine which price to use
        if ($agentId) {
            // Agent booking - use agent cost price
            $dailyPrice = floatval($get('agent_cost_price')) ?: 0;
        } else {
            // Direct booking - use original price
            $dailyPrice = floatval($get('original_price')) ?: 0;
        }

        $totalPrice = $rentalDays * $dailyPrice;

        $set('selling_price', round($totalPrice, 2));

        static::calculateFinalAmount($get, $set);
    }

    // ====================== UNIVERSAL METHODS ======================
    protected static function calculateFinalAmount($get, $set)
    {
        $sellingPrice = floatval($get('selling_price')) ?: 0;
        $additionalCharges = floatval($get('additional_charges')) ?: 0;
        $discountAmount = floatval($get('discount_amount')) ?: 0;

        $finalAmount = max(0, $sellingPrice + $additionalCharges - $discountAmount);

        $set('final_amount', round($finalAmount, 2));

        // Update due amount
        $paidAmount = floatval($get('paid_amount')) ?: 0;
        $set('due_amount', max(0, $finalAmount - $paidAmount));
    }

    protected static function checkAvailability($get, $set)
    {
        $tourPackageId = $get('tour_package_id');
        $serviceDate = $get('service_date');
        $adults = intval($get('adults') ?? 0);
        $children = intval($get('children') ?? 0);
        $totalPax = $adults + $children;

        if (!$tourPackageId || !$serviceDate || $totalPax <= 0) {
            return;
        }

        $tourPackage = TourPackage::find($tourPackageId);
        if (!$tourPackage) {
            return;
        }

        // Check override limit first
        $overrideLimit = TourPackageBookingLimit::where('tour_package_id', $tourPackageId)
            ->where('date', $serviceDate)
            ->first();

        $maxBooking = $overrideLimit ? $overrideLimit->max_booking : $tourPackage->max_booking_per_day;

        // Check existing bookings for this date - Handle missing column gracefully
        try {
            $existingBookings = Booking::where('service_type', 'TOURS')
                ->where('tour_package_id', $tourPackageId)
                ->where('service_date', $serviceDate)
                ->whereNotIn('status', ['cancelled'])
                ->sum(\DB::raw('adults + children'));
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle case where tour_package_id column doesn't exist yet
            if (str_contains($e->getMessage(), 'tour_package_id')) {
                // Fallback: Use tour details relationship
                $existingBookings = Booking::where('service_type', 'TOURS')
                    ->whereHas('tourDetails', function ($query) use ($tourPackageId) {
                        $query->where('tour_package_id', $tourPackageId);
                    })
                    ->where('service_date', $serviceDate)
                    ->whereNotIn('status', ['cancelled'])
                    ->sum(\DB::raw('adults + children'));
            } else {
                throw $e;
            }
        }

        $availableSlots = $maxBooking - $existingBookings;

        // Prevent booking if not enough slots and adjust automatically
        if ($totalPax > $availableSlots) {
            if ($availableSlots > 0) {
                $newAdults = min($adults, $availableSlots);
                $newChildren = max(0, $availableSlots - $newAdults);

                $set('adults', $newAdults);
                $set('children', $newChildren);

                // Recalculate pricing with new passenger count
                static::calculateTotalPricing($get, $set);

                Notification::make()
                    ->warning()
                    ->title('Availability Limited')
                    ->body("Only {$availableSlots} slots available. Passenger count and pricing adjusted automatically.")
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('No Availability')
                    ->body("No slots available for this date. Please choose another date.")
                    ->persistent()
                    ->send();
            }
        }
    }

    protected static function checkCarRentalAvailability($get, $set)
    {
        $packageId = $get('car_rental_package_id');
        $pickupDate = $get('service_date');
        $returnDate = $get('service_end_date');

        if (!$packageId || !$pickupDate || !$returnDate) {
            return;
        }

        $package = CarRentalPackage::find($packageId);
        if (!$package) {
            return;
        }

        $pickupCarbon = Carbon::parse($pickupDate);
        $returnCarbon = Carbon::parse($returnDate);

        // Check availability for each day in the rental period
        $conflictDates = [];

        for ($date = $pickupCarbon->copy(); $date->lte($returnCarbon); $date->addDay()) {
            $availableCars = $package->getAvailableCarsForDate($date->format('Y-m-d'));
            if ($availableCars < 1) {
                $conflictDates[] = $date->format('d M Y');
            }
        }

        if (!empty($conflictDates)) {
            Notification::make()
                ->danger()
                ->title('Car Not Available')
                ->body("Car not available on: " . implode(', ', array_slice($conflictDates, 0, 3)) . (count($conflictDates) > 3 ? ' and ' . (count($conflictDates) - 3) . ' more dates' : ''))
                ->persistent()
                ->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('service_type')
                    ->colors([
                        'success' => 'TOURS',
                        'warning' => 'CAR_RENTAL',
                        'info' => 'FLIGHT',
                        'primary' => 'HOTEL',
                    ]),

                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_passengers')
                    ->label('PAX')
                    ->formatStateUsing(fn ($record) => $record->service_type === 'CAR_RENTAL' ? 'N/A' : "{$record->adults}A + {$record->children}C")
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'partial',
                        'danger' => 'pending',
                        'info' => 'refunded',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'confirmed',
                        'info' => 'active',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                        'warning' => 'draft',
                    ]),

                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agent')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service_type')
                    ->options([
                        'TOURS' => 'Tours',
                        'CAR_RENTAL' => 'Car Rental',
                        'FLIGHT' => 'Flight',
                        'HOTEL' => 'Hotel',
                        'TRANSFER' => 'Transfer',
                        'CRUISE' => 'Cruise',
                        'TRANSPORT' => 'Transport',
                        'VISA' => 'Visa'
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'confirmed' => 'Confirmed',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled'
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                        'zero_payment' => 'Zero Payment'
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('add_payment')
                    ->label('Add Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn ($record) => $record->canMakePayment())
                    ->url(fn ($record) => PaymentResource::getUrl('create') . '?booking_id=' . $record->id),

                Tables\Actions\Action::make('view_payments')
                    ->label('View Payments')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn ($record) => $record->payments()->count() > 0)
                    ->url(fn ($record) => PaymentResource::getUrl('index') . '?tableFilters[booking_id][value]=' . $record->id),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Booking Information')
                    ->schema([
                        Components\TextEntry::make('booking_reference')
                            ->copyable(),
                        Components\TextEntry::make('service_type')
                            ->badge(),
                        Components\TextEntry::make('status')
                            ->badge(),
                        Components\TextEntry::make('payment_status')
                            ->badge(),
                        Components\TextEntry::make('booking_source'),
                        Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Components\Section::make('Customer Details')
                    ->schema([
                        Components\TextEntry::make('customer_name'),
                        Components\TextEntry::make('customer_email')
                            ->copyable(),
                        Components\TextEntry::make('customer_phone')
                            ->copyable(),
                        Components\TextEntry::make('customer_passport_number'),
                        Components\TextEntry::make('adults')
                            ->label('Adults')
                            ->visible(fn ($record) => $record->service_type !== 'CAR_RENTAL'),
                        Components\TextEntry::make('children')
                            ->label('Children')
                            ->visible(fn ($record) => $record->service_type !== 'CAR_RENTAL'),
                    ])
                    ->columns(3),

                Components\Section::make('Service Details')
                    ->schema([
                        Components\TextEntry::make('service_date')
                            ->label(fn ($record) => $record->service_type === 'CAR_RENTAL' ? 'Pickup Date' : 'Service Date')
                            ->date(),
                        Components\TextEntry::make('service_end_date')
                            ->label(fn ($record) => $record->service_type === 'CAR_RENTAL' ? 'Return Date' : 'End Date')
                            ->date(),
                        Components\TextEntry::make('duration_days')
                            ->label('Duration')
                            ->visible(fn ($record) => $record->service_end_date),
                        Components\TextEntry::make('special_requirements')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Components\Section::make('Financial Details')
                    ->schema([
                        Components\TextEntry::make('original_price')
                            ->money('BDT'),
                        Components\TextEntry::make('selling_price')
                            ->money('BDT'),
                        Components\TextEntry::make('additional_charges')
                            ->money('BDT'),
                        Components\TextEntry::make('discount_amount')
                            ->money('BDT'),
                        Components\TextEntry::make('final_amount')
                            ->money('BDT')
                            ->weight('bold'),
                        Components\TextEntry::make('paid_amount')
                            ->money('BDT'),
                        Components\TextEntry::make('due_amount')
                            ->money('BDT'),
                        Components\TextEntry::make('payment_progress')
                            ->suffix('%'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\TourDetailsRelationManager::class,
            RelationManagers\CarRentalDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'draft')->count() > 0 ? 'warning' : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store tour-specific data temporarily
        if ($data['service_type'] === 'TOURS') {
            session([
                'temp_tour_details' => [
                    'pickup_location' => $data['pickup_location'] ?? null,
                    'pickup_time' => $data['pickup_time'] ?? '08:00',
                    'drop_location' => $data['drop_location'] ?? null,
                    'room_type' => $data['room_type'] ?? 'twin',
                    'meal_plan' => $data['meal_plan'] ?? 'breakfast',
                    'guide_language' => $data['guide_language'] ?? 'English',
                    'emergency_contact' => $data['emergency_contact'] ?? null,
                    'tour_notes' => $data['tour_notes'] ?? null,
                ]
            ]);

            // Remove tour fields from booking data
            unset($data['pickup_location'], $data['pickup_time'], $data['drop_location'],
                $data['room_type'], $data['meal_plan'], $data['guide_language'],
                $data['emergency_contact'], $data['tour_notes']);
        }


        // Store car rental data with package ID - UPDATE THIS SECTION
        if ($data['service_type'] === 'CAR_RENTAL') {
            session([
                'temp_car_details' => [
                    'car_rental_package_id' => $data['car_rental_package_id'], // ADD THIS LINE
                    'pickup_date' => $data['service_date'] ?? null,
                    'return_date' => $data['service_end_date'] ?? null,
                ]
            ]);
        }


        return $data;
    }
}
