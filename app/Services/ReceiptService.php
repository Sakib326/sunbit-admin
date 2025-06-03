<?php

// filepath: d:\sunbit-new\sunbit-travels\app\Services\ReceiptService.php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReceiptService
{
    public function generateCustomerReceipt(Booking $booking): Response
    {
        try {
            $data = $this->prepareReceiptData($booking, 'customer');

            $pdf = Pdf::loadView('receipts.customer-receipt', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isRemoteEnabled' => true,
                    'isPhpEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                    'chroot' => public_path(),
                ]);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="customer-receipt-' . $this->cleanText($booking->booking_reference) . '.pdf"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer Receipt Generation Error: ' . $e->getMessage());

            // Return plain text error instead of JSON
            return response('Failed to generate customer receipt. Please try again or contact support.', 500)
                ->header('Content-Type', 'text/plain');
        }
    }

    public function generateAgentReceipt(Booking $booking): Response
    {
        try {
            $data = $this->prepareReceiptData($booking, 'agent');

            $pdf = Pdf::loadView('receipts.agent-receipt', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isRemoteEnabled' => true,
                    'isPhpEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                    'chroot' => public_path(),
                ]);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="agent-receipt-' . $this->cleanText($booking->booking_reference) . '.pdf"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            \Log::error('Agent Receipt Generation Error: ' . $e->getMessage());

            // Return plain text error instead of JSON
            return response('Failed to generate agent receipt. Please try again or contact support.', 500)
                ->header('Content-Type', 'text/plain');
        }
    }

    private function prepareReceiptData(Booking $booking, string $type): array
    {
        $data = [
            'booking' => $booking,
            'type' => $type,
            'company' => [
                'name' => $this->cleanText('Sunbit Travels'),
                'address' => $this->cleanText('Dhaka, Bangladesh'),
                'phone' => $this->cleanText('+880 1234 567890'),
                'email' => $this->cleanText('info@sunbittravels.com'),
                'website' => $this->cleanText('www.sunbittravels.com'),
            ],
            'receipt_date' => now()->format('d M Y, g:i A'),
            'receipt_number' => 'RCP-' . $this->cleanText($booking->booking_reference) . '-' . strtoupper($type),
        ];

        // Add service-specific details
        if ($booking->service_type === 'TOURS') {
            $data['service_details'] = $this->getTourServiceDetails($booking);
        } elseif ($booking->service_type === 'CAR_RENTAL') {
            $data['service_details'] = $this->getCarRentalServiceDetails($booking);
        } else {
            $data['service_details'] = [
                'service_name' => $this->cleanText(ucfirst(str_replace('_', ' ', $booking->service_type))),
                'service_date' => $booking->service_date?->format('d M Y') ?? 'TBD',
            ];
        }

        // Add pricing breakdown
        $data['pricing'] = $this->getPricingBreakdown($booking, $type);

        return $data;
    }

    private function getTourServiceDetails(Booking $booking): array
    {
        return [
            'service_name' => $this->cleanText($booking->tourPackage?->title ?? 'Tour Package'),
            'duration' => $this->cleanText(($booking->tourPackage?->number_of_days ?? 1) . ' Days'),
            'passengers' => $this->cleanText(($booking->adults ?? 0) . ' Adults + ' . ($booking->children ?? 0) . ' Children'),
            'tour_date' => $booking->service_date?->format('d M Y') ?? 'TBD',
            'end_date' => $booking->service_end_date?->format('d M Y') ?? 'TBD',
            'pickup_location' => $this->cleanText($booking->pickup_location ?? 'TBD'),
            'pickup_time' => $this->cleanText($booking->pickup_time ?? '08:00 AM'),
        ];
    }

    private function getCarRentalServiceDetails(Booking $booking): array
    {
        $days = 1;
        if ($booking->service_date && $booking->service_end_date) {
            $days = max(1, $booking->service_date->diffInDays($booking->service_end_date) + 1);
        }

        return [
            'service_name' => $this->cleanText($booking->carRentalPackage?->title ?? 'Car Rental'),
            'car_model' => $this->cleanText($booking->carRentalPackage?->car_brand . ' ' . $booking->carRentalPackage?->car_model ?? 'Vehicle'),
            'rental_period' => $this->cleanText($days . ' Days'),
            'pickup_date' => $booking->service_date?->format('d M Y') ?? 'TBD',
            'return_date' => $booking->service_end_date?->format('d M Y') ?? 'TBD',
            'daily_rate' => 'Tk ' . number_format($booking->original_price ?? 0, 2),
        ];
    }

    private function getPricingBreakdown(Booking $booking, string $type): array
    {
        $pricing = [
            'original_price' => floatval($booking->original_price ?? 0),
            'selling_price' => floatval($booking->selling_price ?? 0),
            'additional_charges' => floatval($booking->additional_charges ?? 0),
            'discount_amount' => floatval($booking->discount_amount ?? 0),
            'final_amount' => floatval($booking->final_amount ?? 0),
            'paid_amount' => floatval($booking->paid_amount ?? 0),
            'due_amount' => floatval($booking->due_amount ?? 0),
        ];

        // For agent receipts, show commission details
        if ($type === 'agent' && $booking->agent_id) {
            $pricing['agent_commission_percent'] = floatval($booking->agent_discount_percent ?? 0);
            $pricing['agent_cost_price'] = floatval($booking->agent_cost_price ?? $booking->original_price ?? 0);
            $pricing['commission_amount'] = $pricing['original_price'] - $pricing['agent_cost_price'];
        }

        return $pricing;
    }

    /**
     * Clean text to ensure UTF-8 compatibility and remove problematic characters
     */
    private function cleanText(?string $text): string
    {
        if (!$text) {
            return '';
        }

        // Convert to string if not already
        $text = (string) $text;

        // Remove null bytes
        $text = str_replace(["\0", '\0'], '', $text);

        // Force UTF-8 encoding
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // Remove control characters except tabs and newlines
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Replace problematic characters
        $text = str_replace(['৳', '₹', '₨'], 'Tk', $text);

        return trim($text);
    }
}
