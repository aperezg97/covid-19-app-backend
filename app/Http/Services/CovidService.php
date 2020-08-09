<?php


namespace App\Http\Services;


use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CovidService
{
    protected $httpClient;

    protected $baseUrl = "https://covid-193.p.rapidapi.com";

    public function __construct()
    {
        $this->httpClient = Http::withHeaders([
            'x-rapidapi-key' => config('app.rapid_api_key'),
            'useQueryString' => 'true'
        ]);
    }

    public function getInfo($continent = null)
    {
        $response = $this->getData(null, $continent);
        $data = collect($response['response']);
        $result = $data->groupBy('continent')
            ->map(function ($value, $key) {
                $object = new \stdClass();
                $object->continent = $key;
                $object->countries = $value;
                return $object;
            })
            ->sort()
            ->flatten()
            ->toArray();

        return $result;
    }

    public function getContinents($continent = null)
    {
        $response = $this->getData(null, $continent);
        $data = collect($response['response']);
        $result = $data->groupBy('continent')
            ->map(function ($value, $key) {
                return $key;
            })
            ->sort()
            ->flatten()
            ->toArray();

        return $result;
    }

    public function getByContinent($continent)
    {
        $result = $this->getInfo($continent);

        if (count($result) == 0)
            return null;
        return $result[0]->countries;
    }

    public function getByCountry($country)
    {
        $response = $this->getData($country);
        $resultsCount = $response['results'];
        if ($resultsCount == 0)
            return null;

        $response = collect($response['response']);
        return $response->first();
    }

    public function getData($country = null, $continent = null)
    {
        $debug = true;

        if ($debug) {
            if ($country != null)
                return $this->getTestData('country', $country);
            if ($continent != null)
                return $this->getTestData('continent', $continent);
            return $this->getTestData();
        }

        try {
            $country = $country ? $country : null;

            $params = [];
            if ($country) {
                $params['country'] = $country;
            }
            if ($continent) {
                $params['$continent'] = $continent;
            }

            return $this->httpClient
                ->get($this->baseUrl . '/statistics', $params)
                ->json();
        } catch (\Exception $exception) {
            // dd($exception);
            Log::info($exception);
            throw new HttpClientException($exception);
        }
    }

    public function getTestData($filterName = null, $filterValue = null)
    {
        $string = file_get_contents("../resources/data.json");
        $jsonData = json_decode($string, true);
        if (isset($filterName)) {
            $jsonData['response'] = collect($jsonData['response'])->where($filterName, $filterValue)->toArray();
            $jsonData['results'] = count($jsonData['response']);
        }
        return [
            'results' => $jsonData['results'],
            'response' => $jsonData['response'],
        ];
    }
}