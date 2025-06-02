<?php

// filepath: app/Filament/Resources/PaymentResource/Pages/CreatePayment.php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Booking;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pre-fill booking if coming from booking page
        if (request()->has('booking_id') && !$data['booking_id']) {
            $data['booking_id'] = request('booking_id');

            $booking = Booking::find($data['booking_id']);
            if ($booking) {
                $data['amount'] = $booking->due_amount;
                $data['currency'] = 'BDT';
                $data['payer_type'] = 'customer';
                $data['payer_id'] = $booking->customer_id;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Update booking payment status after payment creation
        if ($this->record->status === 'completed') {
            $this->record->booking->updatePaymentStatus();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Payment recorded successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
