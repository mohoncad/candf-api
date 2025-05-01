<?php


namespace App\Http\Controllers\API\Supplier;


use App\Http\Controllers\Controller;
use App\Models\Supplier\Supplier;
use Illuminate\Http\Request;
use \Exception;

class FreeSupplierListController extends Controller
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
    public function GetSuppliersFreely(Request $request)
    {
        try {
            $SupplierList = Supplier::where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                    ->where("IsDeleted", "=", 0)
                    ->orderBy('id', 'DESC');

            if ($SupplierList->exists()) {
                $SupplierList = $SupplierList->get();
                foreach ($SupplierList as $Supplier) {
                    $Supplier->title = 'SUP'.$Supplier->Code.' - '.$Supplier->Name;                    
                }
            } else {
                $SupplierList = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free Supplier List Got Successfully!',
                'companyID' => $this->AUTH_USER->CompanyID,
                'supplier_list' => $SupplierList
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the supplier list! ' . $exception->getMessage(),
            ]);

        }
    }
}
