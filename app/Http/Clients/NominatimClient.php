<?php

namespace App\Http\Clients;

use Exception;
use Illuminate\Support\Facades\Http;

class NominatimClient
{
    /**
     * Esegue una reverse geocoding request a Nominatim
     *
     * @param  float  $latitude  Latitudine del punto
     * @param  float  $longitude  Longitudine del punto
     * @param  int  $zoom  Livello di zoom (default 18 per massimo dettaglio)
     * @return array Dati della località
     *
     * @throws Exception Se la richiesta fallisce
     */
    public function reverseGeocode(float $latitude, float $longitude, int $zoom = 18): array
    {
        $response = $this->getHttpClient()->get(
            $this->getReverseGeocodeUrl($latitude, $longitude, $zoom)
        );

        if (! $response->successful()) {
            $errorCode = $response->status();
            $errorBody = $response->body();
            throw new Exception("NominatimClient::reverseGeocode: FAILED: Error {$errorCode}: {$errorBody}");
        }

        $responseData = $response->json();

        if (empty($responseData)) {
            throw new Exception('NominatimClient::reverseGeocode: Empty response');
        }

        return $responseData;
    }

    /**
     * Costruisce l'URL per la reverse geocoding
     */
    private function getReverseGeocodeUrl(float $latitude, float $longitude, int $zoom): string
    {
        return $this->getHost()."/reverse.php?lat={$latitude}&lon={$longitude}&zoom={$zoom}&format=jsonv2";
    }

    /**
     * Restituisce l'host di Nominatim
     */
    protected function getHost(): string
    {
        return rtrim(config('osm2cai.nominatim.host'), '/');
    }

    /**
     * Restituisce il client HTTP con User-Agent personalizzato
     * Nominatim richiede un User-Agent che identifichi l'applicazione
     */
    protected function getHttpClient()
    {
        $userAgent = config('osm2cai.nominatim.user_agent');

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => $userAgent,
        ]);
    }
}
