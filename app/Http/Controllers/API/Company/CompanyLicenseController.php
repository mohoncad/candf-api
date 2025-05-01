<?php


namespace App\Http\Controllers\API\Company;

use App\Http\Controllers\Controller;
use App\Models\MasterSettings\MasterSettings;
use Illuminate\Support\Facades\DB;
use \Illuminate\Http\Request;
use \Exception;

class CompanyLicenseController extends Controller
{
    public static function check($CompanyID) {
        return DB::select("CALL CheckCompanyLicense('{$CompanyID}')")[0];
    }

    public function GetLicenseInfo(Request $request) {

        try {

            $user = auth()->user();
            $CompanyLicense = self::check($user->CompanyID);
            $LicenseStatus = $CompanyLicense->LicenseStatus;
            if ($LicenseStatus == 'Demo') {
                $LicenseStatus = 'DEMO';
            } else if ($LicenseStatus == 'Active') {
                $LicenseStatus = 'ACTIVE';
            } else if ($LicenseStatus == 'Extended') {
                $LicenseStatus = 'EXTENDED';
            } else if ($LicenseStatus == 'Demo Expired') {
                $LicenseStatus = 'DEMO_EXPIRED';
            } else if ($LicenseStatus == 'Expired') {
                $LicenseStatus = 'EXPIRED';
            }

            $MasterSettings = MasterSettings::first();
            $LicenseAlertDays = $MasterSettings->LicenseAlertDays;

            return response()->json([
                'success' => true,
                'message' => 'License Info got successfully!',
                'company_id' => $CompanyLicense->id,
                'company_name' => $CompanyLicense->Name,
                'license_status' => $LicenseStatus,
                'active_days' => $CompanyLicense->ActiveDays,
                'license_alert_days' => $LicenseAlertDays,
            ], 200);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed! ' . $exception->getMessage(),
            ], 200);

        }

    }
}
