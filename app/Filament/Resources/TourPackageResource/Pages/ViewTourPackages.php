<?php

// filepath: app/Filament/Resources/TourPackageResource/Pages/ViewTourPackages.php

namespace App\Filament\Resources\TourPackageResource\Pages;

use App\Filament\Resources\TourPackageResource;
use App\Models\Booking;
use App\Models\Payment;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ViewTourPackages extends ViewRecord
{
    protected static string $resource = TourPackageResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Header Section with Key Information
                Section::make('Package Overview')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Tour Package')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg')
                                    ->columnSpanFull(),

                                TextEntry::make('category.name')
                                    ->label('Category')
                                    ->badge()
                                    ->color('primary'),

                                TextEntry::make('tour_type')
                                    ->label('Tour Type')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'domestic' => 'Domestic Tour',
                                        'international' => 'International Tour',
                                        'local' => 'Local Tour',
                                        default => ucfirst($state)
                                    })
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'danger',
                                    }),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextEntry::make('duration')
                                    ->label('Duration')
                                    ->getStateUsing(fn ($record) => $record->number_of_days . ' Days / ' . $record->number_of_nights . ' Nights')
                                    ->icon('heroicon-m-calendar-days'),

                                TextEntry::make('base_price_adult')
                                    ->label('Adult Price')
                                    ->money('USD')
                                    ->icon('heroicon-m-currency-dollar'),

                                TextEntry::make('base_price_child')
                                    ->label('Child Price')
                                    ->money('USD')
                                    ->icon('heroicon-m-currency-dollar'),

                                TextEntry::make('max_booking_per_day')
                                    ->label('Max Bookings/Day')
                                    ->placeholder('No limit')
                                    ->icon('heroicon-m-user-group'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                IconEntry::make('is_featured')
                                    ->label('Featured')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-star')
                                    ->falseIcon('heroicon-o-star')
                                    ->trueColor('warning')
                                    ->falseColor('gray'),

                                IconEntry::make('is_popular')
                                    ->label('Popular')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-fire')
                                    ->falseIcon('heroicon-o-fire')
                                    ->trueColor('danger')
                                    ->falseColor('gray'),
                            ]),
                    ])
                    ->columnSpanFull(),

                // Tour Route Information
                Section::make('Tour Route')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // FROM Location
                                Section::make('Starting Point (FROM)')
                                    ->schema([
                                        TextEntry::make('fromCountry.name')
                                            ->label('Country')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('fromState.name')
                                            ->label('State/Province')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('fromZella.name')
                                            ->label('District/Zella')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('fromUpazilla.name')
                                            ->label('Area/Upazilla')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('from_location_details')
                                            ->label('Specific Details')
                                            ->placeholder('Not specified')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                // TO Location
                                Section::make('Destination (TO)')
                                    ->schema([
                                        TextEntry::make('toCountry.name')
                                            ->label('Country')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('toState.name')
                                            ->label('State/Province')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('toZella.name')
                                            ->label('District/Zella')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('toUpazilla.name')
                                            ->label('Area/Upazilla')
                                            ->placeholder('Not specified'),

                                        TextEntry::make('to_location_details')
                                            ->label('Specific Details')
                                            ->placeholder('Not specified')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->collapsible(),

                // Tour Description
                Section::make('Tour Details')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->html()
                            ->placeholder('No description provided')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('highlights')
                                    ->label('Tour Highlights')
                                    ->html()
                                    ->placeholder('No highlights specified'),

                                TextEntry::make('tour_schedule')
                                    ->label('Tour Schedule')
                                    ->html()
                                    ->placeholder('No schedule provided'),
                            ]),
                    ])
                    ->collapsible(),

                // Inclusions & Exclusions
                Section::make('Inclusions & Exclusions')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('whats_included')
                                    ->label('What\'s Included')
                                    ->html()
                                    ->placeholder('Not specified'),

                                TextEntry::make('whats_excluded')
                                    ->label('What\'s Excluded')
                                    ->html()
                                    ->placeholder('Not specified'),
                            ]),
                    ])
                    ->collapsible(),

                // Resources & Additional Info
                Section::make('Additional Resources')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('area_map_url')
                                    ->label('Map URL')
                                    ->url()
                                    ->openUrlInNewTab()
                                    ->placeholder('No map provided')
                                    ->copyable(),

                                TextEntry::make('guide_pdf_url')
                                    ->label('Tour Guide PDF')
                                    ->formatStateUsing(fn ($state) => $state ? 'Download PDF Guide' : 'No guide available')
                                    ->url(fn ($state) => $state ? asset('storage/' . $state) : null)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-m-document-arrow-down'),

                                TextEntry::make('agent_commission_percent')
                                    ->label('Agent Commission')
                                    ->suffix('%')
                                    ->placeholder('Not set'),
                            ]),
                    ])
                    ->collapsible(),

                // SEO Information
                Section::make('SEO Settings')
                    ->schema([
                        TextEntry::make('slug')
                            ->label('URL Slug')
                            ->copyable()
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('meta_title')
                                    ->label('Meta Title')
                                    ->placeholder('Using tour title'),

                                TextEntry::make('meta_description')
                                    ->label('Meta Description')
                                    ->placeholder('Not set'),
                            ]),

                        TextEntry::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->badge()
                            ->separator(',')
                            ->placeholder('No keywords set')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Booking Statistics
                Section::make('Booking Statistics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_bookings')
                                    ->label('Total Bookings')
                                    ->getStateUsing(
                                        fn ($record) =>
                                        Booking::where('tour_package_id', $record->id)->count()
                                    )
                                    ->icon('heroicon-m-ticket')
                                    ->color('primary'),

                                TextEntry::make('active_bookings')
                                    ->label('Active Bookings')
                                    ->getStateUsing(
                                        fn ($record) =>
                                        Booking::where('tour_package_id', $record->id)
                                              ->whereIn('status', ['confirmed', 'draft'])
                                              ->count()
                                    )
                                    ->icon('heroicon-m-check-circle')
                                    ->color('success'),

                                TextEntry::make('total_revenue')
                                    ->label('Total Revenue')
                                    ->getStateUsing(
                                        fn ($record) =>
                                        '$' . number_format(
                                            Booking::where('tour_package_id', $record->id)
                                                  ->sum('final_amount'),
                                            2
                                        )
                                    )
                                    ->icon('heroicon-m-currency-dollar')
                                    ->color('success'),

                                TextEntry::make('paid_revenue')
                                    ->label('Paid Revenue')
                                    ->getStateUsing(
                                        fn ($record) =>
                                        '$' . number_format(
                                            Payment::whereHas('booking', function ($query) use ($record) {
                                                $query->where('tour_package_id', $record->id);
                                            })->where('status', 'completed')->sum('amount'),
                                            2
                                        )
                                    )
                                    ->icon('heroicon-m-banknotes')
                                    ->color('warning'),
                            ]),
                    ])
                    ->collapsible(),

                // Recent Bookings
                Section::make('Recent Bookings')
                    ->schema([
                        RepeatableEntry::make('recentBookings')
                            ->label('')
                            ->getStateUsing(
                                fn ($record) =>
                                Booking::where('tour_package_id', $record->id)
                                      ->with(['customer', 'agent'])
                                      ->latest()
                                      ->limit(5)
                                      ->get()
                            )
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('booking_reference')
                                            ->label('Booking ID')
                                            ->weight(FontWeight::Bold),

                                        TextEntry::make('customer_name')
                                            ->label('Customer'),

                                        TextEntry::make('final_amount')
                                            ->label('Amount')
                                            ->money('USD'),

                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'draft' => 'gray',
                                                'confirmed' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                            }),

                                        TextEntry::make('created_at')
                                            ->label('Booked On')
                                            ->date(),
                                    ]),
                            ])
                            ->placeholder('No recent bookings'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Record Timestamps
                Section::make('Record Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime()
                                    ->icon('heroicon-m-plus-circle'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->since()
                                    ->icon('heroicon-m-pencil-square'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewWebsite')
                ->label('View on Website')
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => route('tours.show', $record->slug), shouldOpenInNewTab: true)
                ->color('info'),

            Actions\Action::make('toggleStatus')
                ->label(fn ($record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                ->icon(fn ($record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn ($record) => $record->status === 'active' ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn ($record) => ($record->status === 'active' ? 'Deactivate' : 'Activate') . ' Tour Package')
                ->modalDescription(
                    fn ($record) =>
                    'Are you sure you want to ' .
                    ($record->status === 'active' ? 'deactivate' : 'activate') .
                    ' this tour package? This will ' .
                    ($record->status === 'active' ? 'hide it from' : 'show it on') .
                    ' the website.'
                )
                ->action(function ($record) {
                    $record->status = $record->status === 'active' ? 'inactive' : 'active';
                    $record->save();

                    Notification::make()
                        ->title('Tour package ' . ($record->status === 'active' ? 'activated' : 'deactivated'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('toggleFeatured')
                ->label(fn ($record) => $record->is_featured ? 'Remove Featured' : 'Mark Featured')
                ->icon(fn ($record) => $record->is_featured ? 'heroicon-s-star' : 'heroicon-o-star')
                ->color(fn ($record) => $record->is_featured ? 'gray' : 'warning')
                ->action(function ($record) {
                    $record->is_featured = !$record->is_featured;
                    $record->save();

                    Notification::make()
                        ->title('Tour package ' . ($record->is_featured ? 'marked as featured' : 'removed from featured'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('viewBookings')
                ->label('View Bookings')
                ->icon('heroicon-o-ticket')
                ->url(fn ($record) => route('filament.admin.resources.bookings.index', [
                    'tableFilters' => [
                        'tour_package_id' => ['value' => $record->id]
                    ]
                ]))
                ->color('primary'),

            Actions\Action::make('createBooking')
                ->label('New Booking')
                ->icon('heroicon-o-plus')
                ->url(fn ($record) => route('filament.admin.resources.bookings.create', [
                    'tour_package_id' => $record->id
                ]))
                ->color('success'),

            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Duplicate Tour Package')
                ->modalDescription('This will create a copy of this tour package with "Copy of" prefix.')
                ->action(function ($record) {
                    $newRecord = $record->replicate();
                    $newRecord->title = 'Copy of ' . $record->title;
                    $newRecord->slug = $record->slug . '-copy-' . time();
                    $newRecord->status = 'inactive';
                    $newRecord->is_featured = false;
                    $newRecord->is_popular = false;
                    $newRecord->save();

                    // Copy relationships if needed
                    foreach ($record->itineraries as $itinerary) {
                        $newItinerary = $itinerary->replicate();
                        $newItinerary->tour_package_id = $newRecord->id;
                        $newItinerary->save();
                    }

                    foreach ($record->faqs as $faq) {
                        $newFaq = $faq->replicate();
                        $newFaq->tour_package_id = $newRecord->id;
                        $newFaq->save();
                    }

                    Notification::make()
                        ->title('Tour package duplicated successfully')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->button()
                                ->url(route('filament.admin.resources.tour-packages.edit', $newRecord)),
                        ])
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Tour Package')
                ->modalDescription('Are you sure you want to delete this tour package? This action cannot be undone and will also remove all related bookings, itineraries, and other data.')
                ->successNotificationTitle('Tour package deleted successfully'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add custom widgets here if needed
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // You can add custom widgets here if needed
        ];
    }
}
