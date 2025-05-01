<?php

use App\Models\Bill\bill_entry_list;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/test-db', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['message' => 'Database connected successfully!']);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Database connection failed!', 'message' => $e->getMessage()], 500);
    }
});

//Network activity checker from front-end
Route::get('___server', function () {
    return response()->json([
        'success' => true,
        'message' => 'Server is running!',
    ], 200);
});

Route::middleware('auth:api')
    ->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware(['api'])->group(function () {
    Route::post('auth', 'API\Auth\AuthController@Login');
    Route::post('support_user_auth', 'API\Auth\AuthController@SupportLogin');
    Route::post('logout', 'API\Auth\AuthController@Logout');
    Route::post('recover/password', 'API\Auth\RecoverPasswordController@RequestPasswordReset');
    Route::post('register', 'API\Registration\RegistrationController@Register');
});


Route::middleware(['my.auth', 'company.license'])->group(function () {
    /**
     * Auth user and branch login
     */

    Route::put('branch_login', 'API\Auth\AuthController@BranchLogin');
    Route::get('auth_user', 'API\Auth\AuthController@AuthUserProfile');
    Route::get('company_license_status', 'API\Company\CompanyLicenseController@GetLicenseInfo');

    /**
     * Modules
     */

    Route::get('uap_module_list', 'API\UAP\UAPController@GetAllModules');
    Route::get('my_module_list', 'API\UAP\UAPController@GetMyModules');

    /**
     * User role and permissions
     */

    Route::get('user_role_list', 'API\UAP\UAPController@GetUserRoles');
    Route::get('free_user_role_list', 'API\UAP\FreeUserRoleListController@GetUserRolesFreely');
    Route::post('user_role/modify', 'API\UAP\UAPController@SaveUserRole');
    Route::post('user_role/delete', 'API\UAP\UAPController@DeleteUserRole');
    Route::post('user_role/restore', 'API\UAP\UAPController@RestoreUserRole');
    Route::post('set_role_permissions', 'API\UAP\UAPController@SetUAP');

    /**
     * Users
     */

    Route::get('users', 'API\Users\UsersController@index');
    Route::delete('users/delete', 'API\Users\UsersController@DeleteUser');
    Route::put('users/restore', 'API\Users\UsersController@RestoreUser');
    Route::get('users/profile', 'API\Users\UsersController@SingleUserProfile');
    Route::post('users/profile/save', 'API\Users\UsersController@SaveUserProfile');

    /**
     * Branches
     */

    Route::get('company/branches', 'API\Branch\BranchController@GetAllBranches');
    Route::get('company/branches/free_list', 'API\Branch\FreeBranchListController@GetBranchesFreely');
    Route::get('company/branches/detail', 'API\Branch\BranchController@GetSingleBranch');
    Route::get('company/branches/getUpdatedCode', 'API\Branch\BranchController@GetUpdatedCode');
    Route::delete('company/branches/delete', 'API\Branch\BranchController@DeleteBranch');
    Route::put('company/branches/restore', 'API\Branch\BranchController@RestoreBranch');
    Route::post('company/branches/save', 'API\Branch\BranchController@SaveBranch');

    /**
     * Banks
     */

    Route::get('company/banks', 'API\Bank\BankController@GetAllBanks');
    Route::get('company/banks/free_list', 'API\Bank\FreeBankListController@GetBanksFreely');
    Route::get('company/banks/detail', 'API\Bank\BankController@GetSingleBank');
    Route::delete('company/banks/delete', 'API\Bank\BankController@DeleteBank');
    Route::put('company/banks/restore', 'API\Bank\BankController@RestoreBank');
    Route::post('company/banks/save', 'API\Bank\BankController@SaveBank');

    /**
     * Client groups
     */

    Route::get('company/client-groups', 'API\ClientGroups\ClientGroupController@GetAllClientGroup');
    Route::get('company/client-groups/free_list', 'API\ClientGroups\FreeClientGroupListController@GetClientGroupsFreely');
    Route::get('company/client-groups/detail', 'API\ClientGroups\ClientGroupController@GetSingleClientGroup');
    Route::delete('company/client-groups/delete', 'API\ClientGroups\ClientGroupController@DeleteClientGroup');
    Route::put('company/client-groups/restore', 'API\ClientGroups\ClientGroupController@RestoreClientGroup');
    Route::post('company/client-groups/save', 'API\ClientGroups\ClientGroupController@SaveClientGroup');

    /**
     * Currency lists
     */

    Route::get('company/currency', 'API\Currency\CurrencyController@GetAllCurrency');
    Route::get('company/currency/free_list', 'API\Currency\FreeCurrencyListController@GetCurrencyFreely');
    Route::get('company/currency/detail', 'API\Currency\CurrencyController@GetSingleCurrency');
    Route::delete('company/currency/delete', 'API\Currency\CurrencyController@DeleteCurrency');
    Route::put('company/currency/restore', 'API\Currency\CurrencyController@RestoreCurrency');
    Route::post('company/currency/save', 'API\Currency\CurrencyController@SaveCurrency');

    /**
     * port lists
     */

    Route::get('company/ports', 'API\Port\PortController@GetAllPorts');
    Route::get('company/ports/free_list', 'API\Port\FreePortListController@GetPortsFreely');
    Route::get('company/ports/detail', 'API\Port\PortController@GetSinglePort');
    Route::delete('company/ports/delete', 'API\Port\PortController@DeletePort');
    Route::put('company/ports/restore', 'API\Port\PortController@RestorePort');
    Route::post('company/ports/save', 'API\Port\PortController@SavePort');

    /**
     * unit lists
     */

    Route::get('company/unit', 'API\Unit\UnitController@GetAllUnits');
    Route::get('company/unit/free_list', 'API\Unit\FreeUnitListController@GetUnitsFreely');
    Route::get('company/unit/detail', 'API\Unit\UnitController@GetSingleUnit');
    Route::delete('company/unit/delete', 'API\Unit\UnitController@DeleteUnit');
    Route::put('company/unit/restore', 'API\Unit\UnitController@RestoreUnit');
    Route::post('company/unit/save', 'API\Unit\UnitController@SaveUnit');

    /**
     * Company Info
     */

//    Route::get('company/unit', 'API\Bank\BankController@GetAllBanks');
//    Route::get('company/unit/free_list', 'API\Bank\FreeBankListController@GetBanksFreely');
//    Route::get('company/unit/detail', 'API\Bank\BankController@GetSingleBank');
//    Route::delete('company/unit/delete', 'API\Bank\BankController@DeleteBank');
//    Route::put('company/unit/restore', 'API\Bank\BankController@RestoreBank');
//    Route::post('company/unit/save', 'API\Bank\BankController@SaveBank');

    Route::get('company/detail', 'API\Company\CompanyInfoController@getCompanyDetails');
    Route::post('company/save', 'API\Company\CompanyInfoController@SaveCompanyInfo');
    Route::delete('company/deleteLogo', 'API\Company\CompanyInfoController@DeleteCompanyInfoLogo');

    /**
     * Clients
     */

    Route::get('company/clients', 'API\Client\ClientController@GetAllClients');
    Route::get('company/clients/free_list', 'API\Client\FreeClientListController@GetClientsFreely');
    Route::get('company/clients/detail', 'API\Client\ClientController@GetSingleClient');
    Route::get('company/clients/getUpdatedCode', 'API\Client\ClientController@GetUpdatedCode');
    Route::delete('company/clients/delete', 'API\Client\ClientController@DeleteClient');
    Route::put('company/clients/restore', 'API\Client\ClientController@RestoreClient');
    Route::post('company/clients/save', 'API\Client\ClientController@SaveClient');

    /**
     * Suppliers
     */

    Route::get('company/suppliers', 'API\Supplier\SupplierController@GetAllSuppliers');
    Route::get('company/suppliers/free_list', 'API\Supplier\FreeSupplierListController@GetSuppliersFreely');
    Route::get('company/suppliers/detail', 'API\Supplier\SupplierController@GetSingleSupplier');
    Route::get('company/suppliers/getUpdatedCode', 'API\Supplier\SupplierController@GetUpdatedCode');
    Route::delete('company/suppliers/delete', 'API\Supplier\SupplierController@DeleteSupplier');
    Route::put('company/suppliers/restore', 'API\Supplier\SupplierController@RestoreSupplier');
    Route::post('company/suppliers/save', 'API\Supplier\SupplierController@SaveSupplier');

    /**
     * Import Bill
     */

    //basic APIs
    Route::get("/company/import", "API\Bill\ImportBill\ImportBillController@GetAllImportBill");
    Route::get("/company/import/detail", "API\Bill\ImportBill\ImportBillController@GetSingleImport");
    Route::post("/company/import/save", "API\Bill\ImportBill\ImportBillController@SaveImport");
    Route::delete("/company/import/delete", "API\Bill\ImportBill\ImportBillController@DeleteImport");
    Route::delete("/company/import/deleteAttachments", "API\Bill\ImportBill\ImportBillController@DeleteAttachments");
    Route::put("/company/import/restore", "API\Bill\ImportBill\ImportBillController@RestoreImport");
    Route::get('company/import/getUpdatedCode', 'API\Bill\ImportBill\ImportBillController@GetUpdatedCode');
    
    //get dropdowns
    Route::get("/company/import/getClientDropdown", "API\Bill\ImportBill\ImportBillController@GetClientDropDown");
    Route::get("/company/import/getBankDropdown", "API\Bill\ImportBill\ImportBillController@GetBankDropDown");
    Route::get("/company/import/getSupplierDropdown", "API\Bill\ImportBill\ImportBillController@GetSupplierDropDown");
    Route::get("/company/import/getDocumentDropDown", "API\Bill\ImportBill\ImportBillController@GetDocumentDropDown");
    Route::get("/company/import/getChargeCategoryDropDown", "API\Bill\ImportBill\ImportBillController@GetChargeCategoryDropDown");
    Route::get("/company/import/getChargeHeadDropDown", "API\Bill\ImportBill\ImportBillController@GetChargeHeadDropDown");
    
    //add dropdowns
    Route::post("/company/import/addDocumentDropDown", "API\Bill\ImportBill\ImportBillController@AddDocumentDropDown");
    Route::post("/company/import/addChargeCategoryDropDown", "API\Bill\ImportBill\ImportBillController@AddChargeCategoryDropDown");
    Route::post("/company/import/addChargeHeadDropDown", "API\Bill\ImportBill\ImportBillController@AddChargeHeadDropDown");
    
    //delete dropdowns
    Route::delete("/company/import/deleteBankDropDown", "API\Bill\ImportBill\ImportBillController@DeleteBankDropDown");
    Route::delete("/company/import/deleteSupplierDropDown", "API\Bill\ImportBill\ImportBillController@DeleteSupplierDropDown");
    Route::delete("/company/import/deleteDocumentDropDown", "API\Bill\ImportBill\ImportBillController@DeleteDocumentDropDown");
    Route::delete("/company/import/deleteChargeCategoryDropDown", "API\Bill\ImportBill\ImportBillController@DeleteChargeCategoryDropDown");
    Route::delete("/company/import/deleteChargeHeadDropDown", "API\Bill\ImportBill\ImportBillController@DeleteChargeHeadDropDown");
    Route::delete("/company/import/deleteCurrencyDropDown", "API\Bill\ImportBill\ImportBillController@DeleteCurrencyDropDown");
    Route::delete("/company/import/deletePortDropDown", "API\Bill\ImportBill\ImportBillController@DeletePortDropDown");
    Route::delete("/company/import/deleteUnitDropDown", "API\Bill\ImportBill\ImportBillController@DeleteUnitDropDown");

    /**
     * Export Bill
     */

    //basic APIs
    Route::get("/company/export", "API\Bill\ExportBill\ExportBillController@GetAllExportBill");
    Route::get("/company/export/detail", "API\Bill\ExportBill\ExportBillController@GetSingleExport");
    Route::post("/company/export/save", "API\Bill\ExportBill\ExportBillController@SaveExport");
    Route::delete("/company/export/delete", "API\Bill\ExportBill\ExportBillController@DeleteExport");
    Route::put("/company/export/restore", "API\Bill\ExportBill\ExportBillController@RestoreExport");
    Route::get('company/export/getUpdatedCode', 'API\Bill\ExportBill\ExportBillController@GetUpdatedCode');
    
    //get dropdowns
    Route::get("/company/export/getClientDropdown", "API\Bill\ExportBill\ExportBillController@GetClientDropDown");
    Route::get("/company/export/getBankDropdown", "API\Bill\ExportBill\ExportBillController@GetBankDropDown");
    Route::get("/company/export/getSupplierDropdown", "API\Bill\ExportBill\ExportBillController@GetSupplierDropDown");
    Route::get("/company/export/getDocumentDropDown", "API\Bill\ExportBill\ExportBillController@GetDocumentDropDown");
    Route::get("/company/export/getChargeCategoryDropDown", "API\Bill\ExportBill\ExportBillController@GetChargeCategoryDropDown"); 
    Route::get("/company/export/getChargeHeadDropDown", "API\Bill\ExportBill\ExportBillController@GetChargeHeadDropDown");
    
    //delete dropdown
    Route::delete("/company/export/deleteBankDropDown", "API\Bill\ExportBill\ExportBillController@DeleteBankDropDown");
    Route::delete("/company/export/deleteSupplierDropDown", "API\Bill\ExportBill\ExportBillController@DeleteSupplierDropDown");
    Route::delete("/company/export/deleteDocumentDropDown", "API\Bill\ExportBill\ExportBillController@DeleteDocumentDropDown");
    Route::delete("/company/export/deleteChargeCategoryDropDown", "API\Bill\ExportBill\ExportBillController@DeleteChargeCategoryDropDown");
    Route::delete("/company/export/deleteChargeHeadDropDown", "API\Bill\ExportBill\ExportBillController@DeleteChargeHeadDropDown");

    /**
     * Transport Bill
     */

    //basic APIs
    Route::get("/company/transport", "API\Bill\TransportBill\TransportBillController@GetAllTransportBill");
    Route::get('company/transport/getUpdatedCode', 'API\Bill\TransportBill\TransportBillController@GetUpdatedCode');
    Route::post("/company/transport/save", "API\Bill\TransportBill\TransportBillController@SaveTransport");
    Route::get("/company/transport/detail", "API\Bill\TransportBill\TransportBillController@GetSingleTransport");
    Route::delete("/company/transport/delete", "API\Bill\TransportBill\TransportBillController@DeleteTransport");
    Route::put("/company/transport/restore", "API\Bill\TransportBill\TransportBillController@RestoreTransport");
    
    //get dropdowns
    Route::get("/company/transport/getClientDropdown", "API\Bill\TransportBill\TransportBillController@GetClientDropDown");
    Route::get("/company/transport/getUnitDropdown", "API\Bill\TransportBill\TransportBillController@GetUnitDropDown");
    Route::get("/company/transport/getTransportTypeDropDown", "API\Bill\TransportBill\TransportBillController@GetTransportTypeDropDown");
    Route::get("/company/transport/getChargeHeadDropDown", "API\Bill\TransportBill\TransportBillController@GetChargeHeadDropDown");
    Route::get("/company/transport/getLocationDropDown", "API\Bill\TransportBill\TransportBillController@GetLocationDropDown");
    
    //add dropdown
    Route::post("/company/transport/addTransportTypeDropDown", "API\Bill\TransportBill\TransportBillController@AddTransportTypeDropDown");
    Route::post("/company/transport/addLocationDropDown", "API\Bill\TransportBill\TransportBillController@AddLocationDropDown");
    
    //delete dropdowns
    Route::delete("/company/transport/deleteUnitDropDown", "API\Bill\TransportBill\TransportBillController@DeleteUnitDropDown");
    Route::delete("/company/transport/deleteTransportTypeDropDown", "API\Bill\TransportBill\TransportBillController@DeleteTransportTypeDropDown");
    Route::delete("/company/transport/deleteChargeHeadDropDown", "API\Bill\TransportBill\TransportBillController@DeleteChargeHeadDropDown");
    Route::delete("/company/transport/deleteLocationDropDown", "API\Bill\TransportBill\TransportBillController@DeleteLocationDropDown");


    /**
     * Bill Summary
     */

    Route::get("/company/BillSummary", "API\Bill\BillSummary\BillSummaryController@GetAllBillSummary");
    Route::get("/company/BillSummary/getBillList", "API\Bill\BillSummary\BillSummaryController@GetBillList");
    Route::get("/company/BillSummary/detail", "API\Bill\BillSummary\BillSummaryController@GetSingleBillSummary");
    Route::get('company/BillSummary/getUpdatedCode', 'API\Bill\BillSummary\BillSummaryController@GetUpdatedCode');
    Route::post("/company/BillSummary/save", "API\Bill\BillSummary\BillSummaryController@SaveBillSummary");
    Route::delete("/company/BillSummary/delete", "API\Bill\BillSummary\BillSummaryController@DeleteBillSummary");
    Route::put("/company/BillSummary/restore", "API\Bill\BillSummary\BillSummaryController@RestoreBillSummary");

    /**
     * Bill Payment
     */

    //basic APIs
    Route::get("/company/BillPayment", "API\Bill\BillPayment\BillPaymentController@GetAllBillPayment");
    Route::post("/company/BillPayment/save", "API\Bill\BillPayment\BillPaymentController@SaveBillPayment");
    Route::get("/company/BillPayment/detail", "API\Bill\BillPayment\BillPaymentController@GetSingleBillPayment");
    Route::delete("/company/BillPayment/delete", "API\Bill\BillPayment\BillPaymentController@DeleteBillPayment");
    Route::put("/company/BillPayment/restore", "API\Bill\BillPayment\BillPaymentController@RestoreBillPayment");
    Route::get('company/BillPayment/getUpdatedCode', 'API\Bill\BillPayment\BillPaymentController@GetUpdatedCode');

    //other APIs
    Route::get("/company/BillPayment/getClientWiseBill", "API\Bill\BillPayment\BillPaymentController@GetClientWiseBill");
    Route::get("/company/BillPayment/getClientWiseBillSummary", "API\Bill\BillPayment\BillPaymentController@GetClientWiseBillSummary");
    Route::get("/company/BillPayment/getSummaryWiseBill", "API\Bill\BillPayment\BillPaymentController@getSummaryWiseBill");
});

//Route::get("/import", "API\Bill\ImportBill\ImportBillController@index");


Route::get("/test", "API\UserAccessPermission\UAPController@index");

Route::get('/test-1', function () {
    
    // return response()->json([
    //     'success' => true,
    //     'message' => 'Server is running!',
    // ], 200);

    
    return response()->json([
        'success' => true,
        'data' => DB::table('bill_entry_lists')->where('id', 20)->first(),
    ], 200);
});