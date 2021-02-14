<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Common;

class Admin extends Model
{
    const main_tbl = 'admin_mst';
    const log_tbl = 'admin_log';
    const auth_tbl = 'admin_auth';
    
    public function __construct($request)
    {
        $this->request = $request;
    }

    public function dologin()
    {
        try {
            Log::info("Admin => dologin Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);

            $validator = Validator::make($input_arr, [
                'email_id' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(["Success" => "false", "Message" => "Please Fill Neccesary Information!", "data" => $validator->errors()]);
            }

            $conditions = [];
            $conditions['email_id'] = $input_arr['email_id'];
            $conditions['is_active'] = 1;
            $conditions['password'] = md5($input_arr['password']);
            $users = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();
            if ($users) {
                $insert_arr = [
                    "log_id" => md5($users->email_id . date('Y-m-d H:i:s')),
                    "admin_id" => $users->z_adminid_pk,
                    "log_type" => 1,
                    "added_on" => date('Y-m-d H:i:s'),
                    "added_by" => $users->z_adminid_pk
                ];
                DB::connection()->table(self::log_tbl)->insert($insert_arr);

                DB::connection()->table(self::auth_tbl)->where('admin_id', '=', $users->z_adminid_pk)->delete();

                $auth_insert_arr = [
                    "auth_id" => md5($users->mobile_no . date('Y-m-d H:i:s')),
                    "admin_id" => $users->z_adminid_pk,
                    "expire_on" => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +29 days')),
                    "added_on" => date('Y-m-d H:i:s'),
                    "added_by" => $users->z_adminid_pk
                ];
                DB::connection()->table(self::auth_tbl)->insert($auth_insert_arr);

                $this->request->session()->put('z_adminid_pk', $users->z_adminid_pk);
                $this->request->session()->put('admin_id', $users->admin_id);
                $this->request->session()->put('name', $users->name);
                $this->request->session()->put('email_id', $users->email_id);
                $this->request->session()->put('mobile_no', $users->mobile_no);
                $this->request->session()->put('is_login', 1);
                $this->request->session()->put('building_id', $users->building_id);
                $this->request->session()->put('user_role', $users->user_role);
                return response()->json(["Success" => "true", "Message" => "Login Successfully Done.", "data" => $users]);
            } else {
                return response()->json(["Success" => "false", "Message" => "Invalid Credentials!", "data" => ""]);
            }
        } catch (\Exception $e) {
            Log::info("Admin => dologin Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success" => "false", "Message" => "Exception Caught!", "data" => $e->getMessage()]);
        }
    }

    public function resetpass()
    {
        try {
            Log::info("Admin => resetpass Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'old_password' => 'required',
                'new_password' => 'required',
                'confirm_password' => 'required',
                'admin_id' => 'required'
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }

            if($input_arr['new_password'] != $input_arr['confirm_password']){
                return response()->json(["Success"=> "false","Message" => "New, Confirm Password Missmatch!","data"=>""]);
            }
            
            $conditions = [];
            $conditions['admin_id'] = $input_arr['admin_id'];
            $conditions['password'] = md5($input_arr['old_password']);
            $admindata = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($admindata){
                $update_arr = [
                    "password" => md5($input_arr['new_password']),
                    "modified_on" => date('Y-m-d H:i:s'),
                    "modified_by" => $this->request->session()->get('z_adminid_pk')
                ];
                DB::connection()->table(self::main_tbl)->where('admin_id',$input_arr['admin_id'])->update($update_arr);
                
                $fromemail = env('MAIL_FROM_ADDRESS');
                $data = array(  'username' => $admindata->name,
                                'product_name' => str_replace('_',' ',env('APP_NAME')),
                                'useremail' => $admindata->email_id,
                                'password' => $input_arr['new_password'],
                            );
                $mail_data = $data;
                \Mail::send('mailtemplete.resetpassword', $data, function($message) use ($mail_data,$fromemail) {
                    $message->to($mail_data['useremail'], $mail_data['username'])->subject
                        ('Reset Password from '.$mail_data['product_name']);
                    $message->from($fromemail,$mail_data['product_name']);
                });
                
                Log::info("Admin => Reset Password mail send successfully.");


                return response()->json(["Success"=> "true","Message" => "Password Reset Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Invalid Current Password","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Admin => resetpass Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function forgotpassword()
    {
        try {
            Log::info("Admin => forgotpassword Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'forgotpasswordemail_id' => 'required',
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }

            $conditions = [];
            $conditions['is_active'] = 1;
            $conditions['email_id'] = $input_arr['forgotpasswordemail_id'];
            $admindata = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($admindata){
                $update_arr = [
                    "forgotlink" => url('/a@dmin/forgotpassword/'.md5(date('Y-m-d H:i:s'))),
                    "forgotlink_expire" => date("Y-m-d H:i:s",strtotime(date("Y-m-d H:i:s")." +10 minutes"))
                ];
                DB::connection()->table(self::main_tbl)->where('admin_id',$admindata->admin_id)->update($update_arr);
                
                $fromemail = env('MAIL_FROM_ADDRESS');
                $data = array(  'username' => $admindata->name,
                                'product_name' => str_replace('_',' ',env('APP_NAME')),
                                'useremail' => $admindata->email_id,
                                'forgotlink' => $update_arr['forgotlink'],
                                'forgotlink_expire' => $update_arr['forgotlink_expire'],
                            );
                $mail_data = $data;
                \Mail::send('mailtemplete.forgotpassword', $data, function($message) use ($mail_data,$fromemail) {
                    $message->to($mail_data['useremail'], $mail_data['username'])->subject
                        ('Forgot Password from '.$mail_data['product_name']);
                    $message->from($fromemail,$mail_data['product_name']);
                });
                
                Log::info("Admin => Forgot Password mail send successfully.");


                return response()->json(["Success"=> "true","Message" => "Forgot Password Mail Sent Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "Invalid Email","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Admin => forgotpassword Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }

    public function forgotpasslink()
    {
        try {
            Log::info("Admin => forgotpasslink Method Called ");
            $inputarr = $this->request->input();
            $input_arr = Common::clean_input($inputarr);
            
            $validator = Validator::make($input_arr, [
                'email_id' => 'required',
                'new_password' => 'required',
                'confirm_password' => 'required',
            ]);
           
            if ($validator->fails()) {
                return response()->json(["Success"=> "false","Message" => "Please Fill Neccesary Information!","data"=>$validator->errors()]);
            }

            if($input_arr['new_password'] != $input_arr['confirm_password']){
                return response()->json(["Success"=> "false","Message" => "New, Confirm Password Missmatch!","data"=>""]);
            }

            $conditions = [];
            $conditions['is_active'] = 1;
            $conditions['email_id'] = $input_arr['email_id'];
            $conditions['forgotlink'] = $input_arr['forgotlink'];
            $admindata = DB::connection()->table(self::main_tbl)->where($conditions)->whereNull('deleted_on')->first();

            if($admindata){
                $update_arr = [
                    "password" => md5($input_arr['new_password']),
                ];
                DB::connection()->table(self::main_tbl)->where('admin_id',$admindata->admin_id)->update($update_arr);

                return response()->json(["Success"=> "true","Message" => "Password Reset Successfully","data"=>""]);
            } else {
                return response()->json(["Success"=> "false","Message" => "User Not Found!","data"=>""]);
            }
        } catch (\Exception $e){
            Log::info("Admin => forgotpasslink Method Exception Caught => ");
            Log::info($e->getMessage());
            return response()->json(["Success"=> "false","Message" => "Exception Caught","data"=>$e->getMessage()]);
        }
        
    }
}
