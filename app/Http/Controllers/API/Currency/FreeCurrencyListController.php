<?php


namespace App\Http\Controllers\API\Currency;


use App\Http\Controllers\Controller;
use App\Models\Currency\Currency;
use Illuminate\Http\Request;
use \Exception;

class FreeCurrencyListController extends Controller
{
    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $AUTH_USER;

    public function __construct()
    {
        $this->AUTH_USER = auth()->user();
    }

    /**
     * Freely Get all the user roles
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetCurrencyFreely(Request $request)
    {
        try {
            $currencies = Currency::where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where("IsDeleted", "=", 0)
                ->orderBy('id', 'DESC');


            if ($currencies->exists()) {
                $currencies = $currencies->get();
                foreach ($currencies as $currency) {
                    $currency->title = $currency->Name;
                }
            } else {
                $currencies = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free currency List Got Successfully!',
                'currencies' => $currencies
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the Currencies ! ' . $exception->getMessage(),
            ]);

        }
    }
}
