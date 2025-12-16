<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');


$routes->post('authenticate', 'RegisterController::authenticate');
$routes->post('forgot_password_api', 'RegisterController::forgot_password_api');
$routes->post('reset_password_api', 'RegisterController::reset_password_api');
$routes->post('direct_reset_password_api', 'RegisterController::direct_reset_password_api');
$routes->post('direct_reset_password_api_without_jwt', 'RegisterController::direct_reset_password_api_without_jwt');
$routes->post('add_accesslevel', 'MultiuseController::add_accesslevel');
$routes->post('update_accesslevel', 'MultiuseController::update_accesslevel');
$routes->post('get_accesslevels', 'MultiuseController::get_accesslevels');
$routes->post('add_accesslevelby_usercode', 'MultiuseController::add_accesslevelby_usercode');
$routes->post('get_accesslevelsby_usercode', 'MultiuseController::get_accesslevelsby_usercode');
$routes->post('edit_accesslevelby_usercode', 'MultiuseController::edit_accesslevelby_usercode');
$routes->post('edit_accesslevel', 'MultiuseController::edit_accesslevel');
$routes->post('update_accesslevel_status', 'MultiuseController::update_accesslevel_status');
$routes->post('get_accesslevelsbydepartmentcode', 'MultiuseController::get_accesslevelsbydepartmentcode');

$routes->post('registerEmployee', 'EmployeeController::registerEmployee');
$routes->post('add', 'EmployeeController::add');
$routes->post('addInsuranceDetails', 'EmployeeController::addInsuranceDetails');
$routes->post('addBankDetails', 'EmployeeController::addBankDetails');
$routes->post('addCompany', 'EmployeeController::addCompany');
$routes->post('getCompanyDetails', 'EmployeeController::getCompanyDetails');


$routes->post('getAllEmployeeDetails', 'EmployeeController::getAllEmployeeDetails');
$routes->post('getAllEmployeeDetailsforreport', 'EmployeeController::getAllEmployeeDetailsforreport');

$routes->post('getEmployeeDetailsbyId', 'EmployeeController::getEmployeeDetailsbyId');
$routes->post('getEmployeePersonalData', 'EmployeeController::getEmployeePersonalData');
$routes->post('getBankData', 'EmployeeController::getBankData');
$routes->post('getInsuranceData', 'EmployeeController::getInsuranceData');
$routes->post('getRegisterData', 'EmployeeController::getRegisterData');
$routes->post('gettRegisterData', 'EmployeeController::gettRegisterData');

$routes->post('add_refcodes', 'MultiuseController::add_refcodes');
$routes->post('getroles', 'MultiuseController::getroles');
$routes->post('get_usercode_accesslevel', 'MultiuseController::get_usercode_accesslevel');
$routes->post('getdepartment', 'MultiuseController::getdepartment');
$routes->post('getbranch', 'MultiuseController::getbranch');
$routes->post('getprocessingdocs', 'MultiuseController::getprocessingdocs');
$routes->post('getmarketingemployee', 'MultiuseController::getmarketingemployee');
$routes->post('addTravelCost', 'MultiuseController::addTravelCost');
$routes->post('add_outstation_travel_exp', 'MultiuseController::add_outstation_travel_exp');
$routes->post('getTravelCostList', 'MultiuseController::getTravelCostList');
$routes->post('getOutstationTravelExpList', 'MultiuseController::getOutstationTravelExpList');
$routes->post('getTravelCostListforuser', 'MultiuseController::getTravelCostListforuser');
$routes->post('getOutstationTravelExpListforuser', 'MultiuseController::getOutstationTravelExpListforuser');
$routes->post('edit_outstation_travel_exp', 'MultiuseController::edit_outstation_travel_exp');
$routes->post('editTravelCost', 'MultiuseController::editTravelCost');
$routes->post('add_vacancy', 'MultiuseController::add_vacancy');
$routes->post('edit_vacancy', 'MultiuseController::edit_vacancy');
$routes->post('getallvacancy', 'MultiuseController::getallvacancy');
$routes->post('getallvacancyaftertoday', 'MultiuseController::getallvacancyaftertoday');
$routes->post('addachievement', 'MultiuseController::addachievement');
$routes->post('updateachievement', 'MultiuseController::updateachievement');
$routes->post('getAchievementById', 'MultiuseController::getAchievementById');
$routes->post('getAllAchievements', 'MultiuseController::getAllAchievements');
$routes->post('getTodayAchievements', 'MultiuseController::getTodayAchievements');

$routes->post('getrefcode_bytype', 'MultiuseController::getrefcode_bytype');
$routes->post('add_company', 'CompanyController::add_company');
$routes->post('get_company', 'CompanyController::get_company');
$routes->post('editcompany', 'CompanyController::editcompany');

$routes->get('companylogo/(:any)', 'CompanyController::serveCompanyLogo/$1');

$routes->post('getallcompany', 'CompanyController::getallcompany');
$routes->post('punch_in', 'TimeController::punch_in');
$routes->post('punch_out', 'TimeController::punch_out');
$routes->post('getallleavetype', 'LeaveController::getallleavetype');
$routes->post('add_leave', 'LeaveController::add_leave');
$routes->post('getEmployeeLeaveInfo', 'LeaveController::getEmployeeLeaveInfo');
$routes->post('approveLeave', 'LeaveController::approveLeave');
$routes->post('getallleave', 'LeaveController::getallleave');
$routes->get('leave_attachments/(:any)', 'LeaveController::leave_attachments/$1');
$routes->post('getleavebyusercode', 'LeaveController::getleavebyusercode');
$routes->post('gettodayattendance', 'LeaveController::gettodayattendance');
$routes->post('getuserattendance', 'LeaveController::getuserattendance');
$routes->post('getuserattendanceforadmin', 'LeaveController::getuserattendanceforadmin');
$routes->post('getdatewiseattendance', 'LeaveController::getdatewiseattendance');
$routes->post('getdailytasklist', 'LeaveController::getdailytasklist');
$routes->post('getdailytasklist', 'LeaveController::getdailytasklist');
$routes->post('monthly_attendance', 'LeaveController::getMonthlyAttendance');
$routes->post('user_monthly_attendance', 'LeaveController::getMonthlyAttendanceforuser');
$routes->post('getallleaveforuser', 'LeaveController::getallleaveforuser');
$routes->post('getallleavebyusercodewithcount', 'LeaveController::getallleavebyusercodewithcount');
$routes->post('getleaveapplicationforhod', 'LeaveController::getleaveapplicationforhod');
$routes->post('gettodayleaveemployee', 'LeaveController::gettodayleaveemployee');


$routes->post('designation/add', 'EmployeeController::addDesignations');
$routes->post('designation/update', 'EmployeeController::updateDesignations');
$routes->post('designation/getAll', 'EmployeeController::getAllDesignations');

// MST PROJECT 
$routes->post('project/add', 'ProjectController::addProject');
$routes->post('project/update', 'ProjectController::updateProject');
$routes->post('project/getAll', 'ProjectController::getAllProjects');

// MST TASK MANAGEMENT 
$routes->post('task/add', 'ProjectController::addTask');
$routes->post('task/update', 'ProjectController::updateTask');
$routes->post('task/getAll', 'ProjectController::getAllTasks');
$routes->post('getAllTasksforuser', 'ProjectController::getAllTasksforuser');


$routes->post('holiday/add', 'HolidayController::addHoliday');
$routes->post('holiday/update', 'HolidayController::updateHoliday');
$routes->post('holiday/getAll', 'HolidayController::getAllHolidays');


$routes->post('addClient', 'ClientController::addClient');
$routes->post('updateClient', 'ClientController::updateClient');
$routes->post('getAllClients', 'ClientController::getAllClients');
$routes->post('addInitialClientInteraction', 'ClientController::addInitialClientInteraction');
$routes->post('editInitialClientInteraction', 'ClientController::editInitialClientInteraction');
$routes->post('getAllClientsProcessing_person', 'ClientController::getAllClientsProcessing_person');
$routes->post('getAllClientsProcessing', 'ClientController::getAllClientsProcessing');
$routes->post('client_convert', 'ClientController::client_convert');
$routes->post('getconvertclientlist', 'ClientController::getconvertclientlist');
$routes->post('updatemeetingstatus', 'ClientController::updatemeetingstatus');
$routes->post('markMeetingReached', 'ClientController::markMeetingReached');
$routes->post('getMeetingReachedByCode', 'ClientController::getMeetingReachedByCode');
$routes->post('getAllClientsProcessing_persontoteamlead', 'ClientController::getAllClientsProcessing_persontoteamlead');
$routes->post('getclientsby_user', 'ClientController::getclientsby_user');
$routes->post('getclientlistforteamlead', 'ClientController::getclientlistforteamlead');
$routes->post('getclientlistforhod_ref_code', 'ClientController::getclientlistforhod_ref_code');
$routes->post('supportstafReached', 'ClientController::supportstafReached');
$routes->post('getMeetingReachedbysupportstaf', 'ClientController::getMeetingReachedbysupportstaf');
$routes->post('getfollowUpDaterecords', 'ClientController::getfollowUpDaterecords');
$routes->post('getAllClientstocreatedperson', 'ClientController::getAllClientstocreatedperson');
$routes->post('getAllClientsProcessing_forHOD', 'ClientController::getAllClientsProcessing_forHOD');


$routes->post('add_vehicle', 'VehicleController::add_vehicle');
$routes->post('update_vehicle', 'VehicleController::update_vehicle');
$routes->post('getAllVehicles', 'VehicleController::getAllVehicles');
$routes->post('get_active_vehicles', 'VehicleController::get_active_vehicles');
$routes->post('get_vehicles_from_id', 'VehicleController::get_vehicles_from_id');
$routes->post('delete_vehicle', 'VehicleController::delete_vehicle');
$routes->post('uplodevehicleinfobeforetravale', 'VehicleController::uplodevehicleinfobeforetravale');
$routes->post('updateVehicleInfoBeforeTravale', 'VehicleController::updateVehicleInfoBeforeTravale');
$routes->post('getAllListOfInfoVehicale', 'VehicleController::getAllListOfInfoVehicale');
$routes->post('getVehicleInfoByUserCode', 'VehicleController::getVehicleInfoByUserCode');
$routes->post('request_vehical', 'VehicleController::request_vehical');
$routes->post('approve_vehical_request', 'VehicleController::approve_vehical_request');
$routes->post('getAllVehicleRequests', 'VehicleController::getAllVehicleRequests');
$routes->post('get_vehicles_request_foruser', 'VehicleController::get_vehicles_request_foruser');
$routes->post('releasevehical', 'VehicleController::releasevehical');

$routes->post('allocateLeaves', 'EmployeeController::allocateLeaves');
$routes->post('getEmployeeLeavesbyUserCodeRef', 'EmployeeController::getEmployeeLeavesbyUserCodeRef');

$routes->post('gettodaybirthdayemployee', 'EmployeeController::gettodaybirthdayemployee');
$routes->post('gettodaybirthdayemployeebycode', 'EmployeeController::gettodaybirthdayemployeebycode');
$routes->post('reminder_of_insurance_expiry', 'VehicleController::reminder_of_insurance_expiry');
$routes->post('calculate', 'SalaryController::calculate');
$routes->post('getAttendanceByUserCode', 'SalaryController::getAttendanceByUserCode');
$routes->post('getAttendanceByUserCodefordashoard', 'SalaryController::getAttendanceByUserCodefordashoard');
$routes->post('calculateAll', 'SalaryController::calculateAll');
$routes->post('mark_late_punchin', 'SalaryController::mark_late_punchin');

$routes->post('getteamleader', 'EmployeeController::getteamleader');
$routes->post('getteammember', 'EmployeeController::getteammember');
$routes->post('gethod', 'EmployeeController::gethod');
$routes->post('getSalaryDetails', 'SalaryController::getSalaryDetails');

$routes->post('chat/send', 'ChatController::sendMessage');
$routes->post('getChatHistory', 'ChatController::getChatHistory');
$routes->post('getChatHistoryByUser', 'ChatController::getChatHistoryByUser');
$routes->post('getChatHistoryallUser', 'ChatController::getChatHistoryallUser');
$routes->post('markAsRead', 'ChatController::markAsRead');

$routes->post('updateSalary', 'SalaryController::updateSalary');
$routes->post('addSalary', 'SalaryController::addSalary');
$routes->post('getSalaryDetails', 'SalaryController::getSalaryDetails');
$routes->post('addAppraisal', 'SalaryController::addAppraisal');
$routes->post('getAppraisalByUserCode', 'SalaryController::getAppraisalByUserCode');
$routes->post('getAllAppraisalRecords', 'SalaryController::getAllAppraisalRecords');
$routes->post('getAppraisalById', 'SalaryController::getAppraisalById');

$routes->post('offerlettar', 'LettarsController::offerlettar');
$routes->post('addOfferLetter', 'LettarsController::addOfferLetter');
$routes->post('updateOfferLetter', 'LettarsController::updateOfferLetter');

$routes->post('getAllOfferLetters', 'LettarsController::getAllOfferLetters');
$routes->post('getOfferLetterByIdPost', 'LettarsController::getOfferLetterByIdPost');


$routes->post('addExperienceLetter', 'LettarsController::addExperienceLetter');

$routes->post('updateExperienceLetter', 'LettarsController::updateexperienceletter');
$routes->post('getAllexperienceletters', 'LettarsController::getAllExperienceLetters');
$routes->post('getExperienceLetterById', 'LettarsController::getExperienceLetterById');

$routes->post('addscheme', 'SchemeController::addScheme');
$routes->post('editscheme', 'SchemeController::editScheme');
$routes->post('getSchemeById', 'SchemeController::getSchemeById');
$routes->post('getAllSchemes', 'SchemeController::getAllSchemes');
$routes->post('getcategories', 'SchemeController::getcategories');
$routes->post('getsubSchemebycategories', 'SchemeController::getsubSchemebycategories');



$routes->post('updateRegisterEmployee', 'EmployeeController::updateRegisterEmployee');
$routes->post('updateEmployeeDetails', 'EmployeeController::updateEmployeeDetails');
$routes->post('updateInsuranceDetails', 'EmployeeController::updateInsuranceDetails');
$routes->post('updateBankDetails', 'EmployeeController::updateBankDetails');
$routes->post('getOtherCertificates', 'EmployeeController::getOtherCertificates');
$routes->post('addAssets', to: 'EmployeeController::addAssets');
$routes->post('getAssetsByUser', 'EmployeeController::getAssetsByUser');
$routes->post('getAllUserAssets', 'EmployeeController::getAllUserAssets');
$routes->post('terminateEmployee', 'EmployeeController::terminateEmployee');
$routes->post('getTerminationData', 'EmployeeController::getTerminationData');


$routes->post('addStageoneform', 'ProcessingController::addStageoneform');

$routes->post('getInternData', 'EmployeeController::getInternData');

$routes->post('addStagesecondform', 'ProcessingController::addStagesecondform');
$routes->post('addStagetreeform', 'ProcessingController::addStagetreeform');
$routes->post('upadateaddStageoneform', 'ProcessingController::upadateaddStageoneform');
$routes->post('getStageoneform', 'ProcessingController::getStageoneform');
$routes->post('getStagetwoform', 'ProcessingController::getStagetwoform');
$routes->post('getStagetreeform', 'ProcessingController::getStagetreeform');
$routes->post('updatestagesecondform', 'ProcessingController::updatestagesecondform');
$routes->post('updatestagetreeform', 'ProcessingController::updatestagetreeform');
$routes->post('asignStagepersons', 'ProcessingController::asignStagepersons');
$routes->post('getstegeoneallotpersonsList', 'ProcessingController::getstegeoneallotpersonsList');
$routes->post('getstegetoperson', 'ProcessingController::getstegetoperson');
$routes->post('addclaim_processing', 'ProcessingController::addclaim_processing');
$routes->post('update_claim_processing', 'ProcessingController::update_claim_processing');
$routes->post('getClaimProcessingById', 'ProcessingController::getClaimProcessingById');
$routes->post('getAllClaimProcessing', 'ProcessingController::getAllClaimProcessing');
$routes->post('add_ED_processing', 'ProcessingController::add_ED_processing');
$routes->post('update_ED_processing', 'ProcessingController::update_ED_processing');
$routes->post('getEDProcessingById', 'ProcessingController::getEDProcessingById');
$routes->post('getAllEDProcessing', 'ProcessingController::getAllEDProcessing');


$routes->post('addTraining', 'TrainingController::addTraining');
$routes->post('updateTraining', 'TrainingController::updateTraining');
$routes->post('getAllTraining', 'TrainingController::getAllTraining');
$routes->post('getInternById', 'TrainingController::getInternById');
$routes->post('getAllInterns', 'TrainingController::getAllInterns');
$routes->post('addInternshipLetter', 'TrainingController::addInternshipLetter');
$routes->post('updateInternshipLetter', 'TrainingController::updateInternshipLetter');

$routes->post('getAllInternshipLetters', 'TrainingController::getAllInternshipLetters');
$routes->post('getInternshipLetterById', 'TrainingController::getInternshipLetterById');

$routes->post('getOngoingTraining', 'TrainingController::getOngoingTraining');


$routes->post('addPolicy', 'LettarsController::addPolicy');
$routes->post('updatePolicy', 'LettarsController::updatePolicy');
$routes->post('getAllPolicies', 'LettarsController::getAllPolicies');

$routes->post('addTask', 'LettarsController::addTask');
$routes->post('updateTask', 'LettarsController::updateTask');
$routes->post('getAllTasks', 'LettarsController::getAllTasks');


$routes->post('queries-complaints/add', 'QueriesComplaintsController::add');
$routes->post('queries-complaints/update', 'QueriesComplaintsController::update');
$routes->post('queries-complaints/getById', 'QueriesComplaintsController::getById');
$routes->post('queries-complaints/getByType', 'QueriesComplaintsController::getByType');
$routes->post('queries-complaints/getAll', 'QueriesComplaintsController::getAll');
$routes->post('queries-complaints/getbyusercode', 'QueriesComplaintsController::getbyusercode');


$routes->post('add_event', 'EventController::add_event');
$routes->post('update_event', 'EventController::update_event');
$routes->post('get_all_events', 'EventController::get_all_events');
$routes->post('get_event_by_id', 'EventController::get_event_by_id');
$routes->post('delete_event', 'EventController::delete_event');
$routes->post('getactiveVhehicles', 'VehicleController::getactiveVhehicles');
$routes->post('get_upcoming_events', 'EventController::get_upcoming_events');




$routes->post('add_project', 'ArchiethosController::add_project');
$routes->post('update_project', 'ArchiethosController::update_project');
$routes->post('get_all_projects', 'ArchiethosController::get_all_projects');
$routes->post('get_project_by_id', 'ArchiethosController::get_project_by_id');

$routes->post('add_task', 'ArchiethosController::add_task');
$routes->post('update_task', 'ArchiethosController::update_task');
$routes->post('get_all_tasks', 'ArchiethosController::get_all_tasks');
$routes->post('get_task_by_id', 'ArchiethosController::get_task_by_id');
$routes->post('get_tasks_by_project_id', 'ArchiethosController::get_tasks_by_project_id');

$routes->post('add_project_status', 'ArchiethosController::add_project_status');
$routes->post('update_project_status', 'ArchiethosController::update_project_status');
$routes->post('get_all_project_status', 'ArchiethosController::get_all_project_status');
$routes->post('get_project_status_by_project_id', 'ArchiethosController::get_project_status_by_project_id');

$routes->post('add_site_visit', 'ArchiethosController::add_site_visit');
$routes->post('update_site_visit', 'ArchiethosController::update_site_visit');
$routes->post('get_all_site_visits', 'ArchiethosController::get_all_site_visits');
$routes->post('get_site_visits_by_project', 'ArchiethosController::get_site_visits_by_project');

$routes->post('add_vendor', 'ArchiethosController::add_vendor');
$routes->post('update_vendor', 'ArchiethosController::update_vendor');
$routes->post('get_vendor_by_id', 'ArchiethosController::get_vendor_by_id');
$routes->post('get_all_vendors', 'ArchiethosController::get_all_vendors');
$routes->post('get_archiethos_emp', 'ArchiethosController::get_archiethos_emp');
$routes->post('get_task_by_usercode', 'ArchiethosController::get_task_by_usercode');




$routes->post('add_payment', 'AccountController::add_payment');
$routes->post('add_tax_invoice', 'AccountController::add_tax_invoice');
$routes->post('add_bank', 'AccountController::add_bank');
$routes->post('get_bank_by_id', 'AccountController::get_bank_by_id');

$routes->post('purchase_order', 'AccountController::purchase_order');
$routes->post('add_credit_note', 'AccountController::add_credit_note');
$routes->post('add_debit_note', 'AccountController::add_debit_note');
$routes->post('add_yearly_budget', 'AccountController::add_yearly_budget');
$routes->post('add_alerts', 'AccountController::add_alerts');

$routes->post('get_all_payments', 'AccountController::get_all_payments');
$routes->post('get_all_tax_invoices', 'AccountController::get_all_tax_invoices');
$routes->post('get_all_banks', 'AccountController::get_all_banks');
$routes->post('get_all_purchase_orders', 'AccountController::get_all_purchase_orders');
$routes->post('get_all_credit_notes', 'AccountController::get_all_credit_notes');
$routes->post('get_all_debit_notes', 'AccountController::get_all_debit_notes');
$routes->post('get_all_yearly_budgets', 'AccountController::get_all_yearly_budgets');
$routes->post('get_all_alerts', 'AccountController::get_all_alerts');
$routes->post('getalltaxinvoicesswithcalculation', 'AccountController::getalltaxinvoicesswithcalculation');
$routes->post('getalltaxinvoicesbycompany', 'AccountController::getalltaxinvoicesbycompany');
$routes->post('updateutr_no', 'AccountController::updateutr_no');


$routes->post('update_payment', 'AccountController::update_payment');
$routes->post('update_tax_invoice', 'AccountController::update_tax_invoice');
$routes->post('update_bank', 'AccountController::update_bank');
$routes->post('update_purchase_order', 'AccountController::update_purchase_order');
$routes->post('update_credit_note', 'AccountController::update_credit_note');
$routes->post('update_debit_note', 'AccountController::update_debit_note');
$routes->post('update_yearly_budget', 'AccountController::update_budget');
$routes->post('update_alert', 'AccountController::update_alert');


$routes->post('get_tax_invoice_by_code', 'AccountController::get_tax_invoice_by_code');
$routes->post('get_purchase_order_by_code', 'AccountController::get_purchase_order_by_code');
$routes->post('get_credit_note_by_code', 'AccountController::get_credit_note_by_code');
$routes->post('get_debit_note_by_code', 'AccountController::get_debit_note_by_code');

$routes->post('updatepaymentstatus', 'AccountController::updatePaymentStatus');
$routes->post('updatereimarsmentpaymentstatus', 'AccountController::updatereimarsmentpaymentstatus');


$routes->post('getemployeealldatareport', 'ReportController::getemployeealldatareport');

$routes->post('add_meeting', 'MeetingController::add_meeting');
$routes->post('update_meeting', 'MeetingController::update_meeting');
$routes->post('delete_meeting', 'MeetingController::delete_meeting');
$routes->post('get_all_meetings', 'MeetingController::get_all_meetings');
$routes->post('get_meeting_by_id', 'MeetingController::get_meeting_by_id');
$routes->post('get_meetings_by_employee_id', 'MeetingController::get_meetings_by_employee_id');
$routes->post('get_upcoming_meetings', 'MeetingController::get_upcoming_meetings');

$routes->post('get_latlng_from_url', 'TimeController::get_latlng_from_url');



$routes->post('project_finance_add', 'ProjectfinanceController::project_finance_add');
$routes->post('project_finance_update', 'ProjectfinanceController::project_finance_update');
$routes->post('getProjectFinanceById', 'ProjectfinanceController::getProjectFinanceById');
$routes->post('getallProject_Finance', 'ProjectfinanceController::getallProject_Finance');

$routes->post('cgov_finance_add', 'GovernmentController::addcgov');
$routes->post('updatecgov', 'GovernmentController::updatecgov');
$routes->post('getByIdcgov', 'GovernmentController::getByIdcgov');
$routes->post('getAllcgov', 'GovernmentController::getAllcgov');


$routes->post('add_land_quota', 'LandquotaController::add_land_quota');
$routes->post('update_land_quota', 'LandquotaController::update_land_quota');
$routes->post('getAll_land_quota', 'LandquotaController::getAll_land_quota');
$routes->post('getById_land_quota', 'LandquotaController::getById_land_quota');
$routes->post('addInvestmentInLand', 'LandquotaController::addInvestmentInLand');
$routes->post('updateInvestmentInLand', 'LandquotaController::updateInvestmentInLand');
$routes->post('getAllInvestmentInLand', 'LandquotaController::getAllInvestmentInLand');
$routes->post('getByIdInvestmentInLand', 'LandquotaController::getByIdInvestmentInLand');
$routes->post('add_land_Enquiry', 'LandquotaController::add_land_Enquiry');
$routes->post('update_land_Enquiry', 'LandquotaController::update_land_Enquiry');
$routes->post('getAll_land_Enquiry', 'LandquotaController::getAll_land_Enquiry');
$routes->post('getById_land_Enquiry', 'LandquotaController::getById_land_Enquiry');


$routes->post('reminder_of_insurance_expiry', 'VehicleController::reminder_of_insurance_expiry');



$routes->post('addDaySpeciality', 'DaySpecialityController::addDaySpeciality');
$routes->post('updateDaySpeciality', 'DaySpecialityController::updateDaySpeciality');

$routes->post('getAllDaySpecialities', 'DaySpecialityController::getAllDaySpecialities');

$routes->post('getTodaySpeciality', 'DaySpecialityController::getTodaySpeciality');

$routes->post('deleteRecord', 'MultiuseController::deleteRecord');

// In app/Config/Routes.php
$routes->post('add_HSNSAC', 'HSNSACController::add_HSNSAC');
$routes->post('getallHSNSAC', 'HSNSACController::getallHSNSAC');
$routes->post('getHSNSAC', 'HSNSACController::getHSNSAC');
$routes->post('editHSNSAC', 'HSNSACController::editHSNSAC');
$routes->post('deleteHSNSAC', 'HSNSACController::deleteHSNSAC');
$routes->get('searchHSNSAC', 'HSNSACController::searchHSNSAC');

// Signature and Stamp Routes
$routes->post('add_signatureandstamp', 'SignatureandstampController::add_signatureandstamp');
$routes->post('getallsignatureandstamp', 'SignatureandstampController::getallsignatureandstamp');
$routes->post('get_signatureandstamp', 'SignatureandstampController::get_signatureandstamp');
$routes->post('editsignatureandstamp', 'SignatureandstampController::editsignatureandstamp');
$routes->post('deletesignatureandstamp', 'SignatureandstampController::deletesignatureandstamp');
$routes->get('uploads/signatures/(:any)', 'SignatureandstampController::serveSignatureImage/$1');
$routes->get('uploads/stamps/(:any)', 'SignatureandstampController::serveStampImage/$1');


// Add this route for debugging
$routes->post('debug-upload', 'SignatureandstampController::debugUpload');

// Update your existing routes to use writable path
$routes->get('writable/uploads/signatures/(:any)', 'SignatureandstampController::serveSignatureImage/$1');
$routes->get('writable/uploads/stamps/(:any)', 'SignatureandstampController::serveStampImage/$1');

$routes->post('delete-note', 'MultiuseController::softDeleteWithChildren');




$routes->post('add_assetss', 'AssetsController::add_assets');
$routes->post('get_assetss', 'AssetsController::get_assets');
$routes->post('getallAssetss', 'AssetsController::getallAssets');
$routes->post('editAssetss', 'AssetsController::editAssets');
$routes->post('deleteAssetss', 'AssetsController::deleteAssets');

$routes->post('updateAsset', 'EmployeeController::updateAsset');
$routes->post('deleteAsset', 'EmployeeController::deleteAsset');

$routes->post('delete_invoice', 'AccountController::delete_invoice');

$routes->post('delete_purchase_order', 'AccountController::delete_purchase_order');


$routes->post('calculateSalaries', 'EmployeeController::calculateSalaries');
$routes->post('getSalaryReport', 'EmployeeController::getSalaryReport');
$routes->post('calculateAll', 'EmployeeController::calculateSalaries'); // Alias for frontend compat

$routes->post('getRegisterDatawp', 'EmployeeController::getRegisterDatawp');


// Item routes
$routes->post('add_item', 'IteamsController::add_item');
$routes->post('get_item', 'IteamsController::get_item');
$routes->post('get_all_items', 'IteamsController::get_all_items');
$routes->post('get_items_by_vendor', 'IteamsController::get_items_by_vendor');
$routes->post('update_item', 'IteamsController::update_item');
$routes->post('delete_item', 'IteamsController::delete_item');
$routes->post('search_items', 'IteamsController::search_items');



// Water supply routes
$routes->post('add_water', 'WaterController::add_water');
$routes->post('get_water', 'WaterController::get_water');
$routes->post('get_all_waters', 'WaterController::get_all_waters');
$routes->post('get_waters_by_employee_id', 'WaterController::get_waters_by_employee_id');
$routes->post('update_water', 'WaterController::update_water');
$routes->post('delete_water', 'WaterController::delete_water');
$routes->post('search_waters', 'WaterController::search_waters');

$routes->post('addHousekeeping', 'SuppliesServicesController::addHousekeeping');
$routes->post('editHousekeeping', 'SuppliesServicesController::editHousekeeping');
$routes->post('getAllHousekeepings', 'SuppliesServicesController::getAllHousekeepings');

$routes->post('addTeaSnacks', 'SuppliesServicesController::addTeaSnacks');
$routes->post('editTeaSnacks', 'SuppliesServicesController::editTeaSnacks');
$routes->post('getTeaSnacks', 'SuppliesServicesController::getTeaSnacks');
