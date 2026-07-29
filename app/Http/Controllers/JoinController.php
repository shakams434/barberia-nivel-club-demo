<?php

namespace App\Http\Controllers;

use App\Models\Business;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class JoinController extends Controller
{
    public function show(string $businessSlug): View
    {
        $business = Business::where('slug', $businessSlug)->where('active', true)->firstOrFail();

        return view('join.show', [
            'business' => $business,
            'whatsappUrl' => $this->whatsappUrl($business),
        ]);
    }

    public function qr(Request $request, string $businessSlug): Response
    {
        $business = Business::where('slug', $businessSlug)->where('active', true)->firstOrFail();
        $renderer = new ImageRenderer(new RendererStyle(480, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($this->whatsappUrl($business));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="qr-'.$business->slug.'.svg"',
        ]);
    }

    private function whatsappUrl(Business $business): string
    {
        $phone = preg_replace('/\D+/', '', $business->whatsappAccount?->phone_e164 ?? $business->contact_phone ?? '');

        return 'https://wa.me/'.$phone.'?text='.rawurlencode('QUIERO UNIRME');
    }
}
