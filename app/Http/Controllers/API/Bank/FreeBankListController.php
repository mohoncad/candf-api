<?php


namespace App\Http\Controllers\API\Bank;


use App\Http\Controllers\Controller;
use App\Models\Bank\Bank;
use Illuminate\Http\Request;
use \Exception;

class FreeBankListController extends Controller
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
    public function GetBanksFreely(Request $request)
    {
        try {
            $BankList = Bank::where("CompanyID", "=", $this->AUTH_USER->CompanyID)->where("IsDeleted", "=", 0)->orderBy('id', 'DESC');


            if ($BankList->exists()) {
                $BankList = $BankList->get();
                foreach ($BankList as $Bank) {
                    $Bank->title = $Bank->Name;
                }
            } else {
                $BankList = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free bank List Got Successfully!',
                'bank_list' => $BankList
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the bank list! ' . $exception->getMessage(),
            ]);

        }
    }
}
