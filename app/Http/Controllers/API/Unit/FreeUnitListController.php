<?php


namespace App\Http\Controllers\API\Unit;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \Exception;
use App\Models\Unit\Unit;
// use SebastianBergmann\CodeCoverage\Report\Xml\Unit;

class FreeUnitListController extends Controller
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
    public function GetUnitsFreely(Request $request)
    {
        try {
            $units = Unit::where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where("IsDeleted", "=", 0)
                ->orderBy('id', 'DESC');


            if ($units->exists()) {
                $units = $units->get();
                foreach ($units as $unit) {
                    $unit->title = $unit->Name;
                }
            } else {
                $units = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free Units Got Successfully!',
                'units' => $units
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the units ! ' . $exception->getMessage(),
            ]);

        }
    }
}
