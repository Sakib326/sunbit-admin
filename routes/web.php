<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/booking/{booking}/customer-receipt', function ($bookingId) {
    $booking = \App\Models\Booking::findOrFail($bookingId);
    $receiptService = new \App\Services\ReceiptService();
    return $receiptService->generateCustomerReceipt($booking);
})->name('booking.customer-receipt');

Route::get('/booking/{booking}/agent-receipt', function ($bookingId) {
    $booking = \App\Models\Booking::findOrFail($bookingId);
    $receiptService = new \App\Services\ReceiptService();
    return $receiptService->generateAgentReceipt($booking);
})->name('booking.agent-receipt');
