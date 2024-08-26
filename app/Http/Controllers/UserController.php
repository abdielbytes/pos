<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\role_user;
use App\Models\product_branch;
use App\Models\branch;
use App\Models\Userbranch;
use App\utils\helpers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\ImageManagerStatic as Image;
use \Nwidart\Modules\Facades\Module;
use App\Models\Sale;

class UserController extends BaseController
{

    //------------- GET ALL USERS---------\\

    public function index(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'username', 1 => 'statut', 2 => 'phone', 3 => 'email');
        $param = array(0 => 'like', 1 => '=', 2 => 'like', 3 => 'like');
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $users = User::where(function ($query) use ($ShowRecord) {
            if (!$ShowRecord) {
                return $query->where('id', '=', Auth::user()->id);
            }
        });

        //Multiple Filter
        $Filtred = $helpers->filter($users, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('username', 'LIKE', "%{$request->search}%")
                        ->orWhere('firstname', 'LIKE', "%{$request->search}%")
                        ->orWhere('lastname', 'LIKE', "%{$request->search}%")
                        ->orWhere('email', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });
        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $users = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $roles = Role::where('deleted_at', null)->get(['id', 'name']);
        $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
            'totalRows' => $totalRows,
        ]);
    }

    //------------- GET USER Auth ---------\\

    public function GetUserAuth(Request $request)
    {
        $helpers = new helpers();
        $user['avatar'] = Auth::user()->avatar;
        $user['username'] = Auth::user()->username;
        $user['currency'] = $helpers->Get_Currency();
        $user['logo'] = Setting::first()->logo;
        $user['branch'] = Auth::user()->assignedbranches;
        $user['default_language'] = Setting::first()->default_language;
        $user['footer'] = Setting::first()->footer;
        $user['developed_by'] = Setting::first()->developed_by;
        $permissions = Auth::user()->roles()->first()->permissions->pluck('name');
        $products_alerts = product_branch::join('products', 'product_branch.product_id', '=', 'products.id')
            ->whereRaw('qte <= stock_alert')
            ->where('product_branch.deleted_at', null)
            ->count();

        return response()->json([
            'success' => true,
            'user' => $user,
            'notifs' => $products_alerts,
            'permissions' => $permissions,
        ]);
    }

    //------------- GET USER ROLES ---------\\

    public function GetUserRole(Request $request)
    {

        $roles = Auth::user()->roles()->with('permissions')->first();

        $data = [];
        if ($roles) {
            foreach ($roles->permissions as $permission) {
                $data[] = $permission->name;

            }
            return response()->json(['success' => true, 'data' => $data]);
        }

    }

    //------------- STORE NEW USER ---------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', User::class);
        $this->validate($request, [
            'email' => 'required|unique:users',
        ], [
            'email.unique' => 'This Email already taken.',
        ]);
        \DB::transaction(function () use ($request) {
            $avatar = $this->handleImageUpload($request, 'avatar', 'no_avatar.png');
            $g1avatar = $this->handleImageUpload($request, 'g1avatar', 'no-image.png');

            $g2avatar = $this->handleImageUpload($request, 'g2avatar', 'no-image.png');
            $house = $this->handleImageUpload($request, 'house', 'no-image.png');
           if($request['is_all_branches'] == '1' || $request['is_all_branches'] == 'true'){
                $is_all_branches = 1;
            }else{
                $is_all_branches = 0;
            }
            echo($request['nameg1']);
            $User = new User;
            $User->firstname = $request['firstname'];
            $User->lastname  = $request['lastname'];
            $User->username  = $request['username'];
            $User->email     = $request['email'];
            $User->phone     = $request['phone'];
            $User->password  = Hash::make($request['password']);
            $User->address  = $request['adresse'];

            $User->avatar    = $avatar;

            $User->g1avatar    = $g1avatar;
            $User->g2avatar    = $g2avatar;

            $User->house = $house;

            $User->role_id   = $request['role'];
            $User->is_all_branches   = $is_all_branches;
            $User->Guarantor1_name = $request['nameg1'];
            $User->Guarantor1_phone = $request['phoneg1'];
            $User->Guarantor1_house = $request['houseg1'];

            $User->Guarantor2_name = $request['nameg2'];
            $User->Guarantor2_phone = $request['phoneg2'];
            $User->Guarantor2_house = $request['houseg2'];

            $User->bank = $request['bank'];
            $User->account_number = $request['account_number'];

            $User->save();

            $role_user = new role_user;
            $role_user->user_id = $User->id;
            $role_user->role_id = $request['role'];
            $role_user->save();

            if(!$User->is_all_branches){
                $User->assignedbranches()->sync($request['assigned_to']);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    private function handleImageUpload($request, $fieldName, $default)
    {
        if ($request->hasFile($fieldName)) {
            $image = $request->file($fieldName);
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();
            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(128, 128);
            $image_resize->save(public_path('/images/avatar/' . $filename));
            return $filename;
        }
        return $default;
    }
    //------------ function show -----------\\

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $assigned_branches = Userbranch::where('user_id', $id)->pluck('branch_id')->toArray();
        $branches = branch::where('deleted_at', '=', null)->whereIn('id', $assigned_branches)->pluck('id')->toArray();

        return response()->json([
            'assigned_branches' => $branches,
        ]);
    }

    //------------- UPDATE  USER ---------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $this->validate($request, [
            'email' => 'required|email|unique:users',
            'email' => Rule::unique('users')->ignore($id),
        ], [
            'email.unique' => 'This Email already taken.',
        ]);

        \DB::transaction(function () use ($id ,$request) {
            $user = User::findOrFail($id);
            $current = $user->password;

            if ($request->NewPassword != 'null') {
                if ($request->NewPassword != $current) {
                    $pass = Hash::make($request->NewPassword);
                } else {
                    $pass = $user->password;
                }

            } else {
                $pass = $user->password;
            }

            $currentAvatar = $user->avatar;
            if ($request->avatar != $currentAvatar) {

                $image = $request->file('avatar');
                $path = public_path() . '/images/avatar';
                $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(128, 128);
                $image_resize->save(public_path('/images/avatar/' . $filename));

                $userPhoto = $path . '/' . $currentAvatar;
                if (file_exists($userPhoto)) {
                    if ($user->avatar != 'no_avatar.png') {
                        @unlink($userPhoto);
                    }
                }
            } else {
                $filename = $currentAvatar;
            }

            if($request['is_all_branches'] == '1' || $request['is_all_branches'] == 'true'){
                $is_all_branches = 1;
            }else{
                $is_all_branches = 0;
            }

            User::whereId($id)->update([
                'firstname' => $request['firstname'],
                'lastname' => $request['lastname'],
                'username' => $request['username'],
                'email' => $request['email'],
                'phone' => $request['phone'],
                'password' => $pass,
                'avatar' => $filename,
                'statut' => $request['statut'],
                'is_all_branches' => $is_all_branches,
                'role_id' => $request['role'],

            ]);

            role_user::where('user_id' , $id)->update([
                'user_id' => $id,
                'role_id' => $request['role'],
            ]);

            $user_saved = User::where('deleted_at', '=', null)->findOrFail($id);
            $user_saved->assignedbranches()->sync($request['assigned_to']);

        }, 10);

        return response()->json(['success' => true]);

    }


    //------------- UPDATE PROFILE ---------\\

    public function updateProfile(Request $request)
    {
        $id = Auth::user()->id;
        $user = User::findOrFail($id);
        $current = $user->password;

        if ($request->NewPassword != 'undefined') {
            if ($request->NewPassword != $current) {
                $pass = Hash::make($request->NewPassword);
            } else {
                $pass = $user->password;
            }

        } else {
            $pass = $user->password;
        }

        $currentAvatar = $user->avatar;
        if ($request->avatar != $currentAvatar) {

            $image = $request->file('avatar');
            $path = public_path() . '/images/avatar';
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(128, 128);
            $image_resize->save(public_path('/images/avatar/' . $filename));

            $userPhoto = $path . '/' . $currentAvatar;

            if (file_exists($userPhoto)) {
                if ($user->avatar != 'no_avatar.png') {
                    @unlink($userPhoto);
                }
            }
        } else {
            $filename = $currentAvatar;
        }

        User::whereId($id)->update([
            'firstname' => $request['firstname'],
            'lastname' => $request['lastname'],
            'username' => $request['username'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => $pass,
            'avatar' => $filename,

        ]);

        return response()->json(['avatar' => $filename, 'user' => $request['username']]);

    }

    //----------- IsActivated (Update Statut User) -------\\

    public function IsActivated(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $user = Auth::user();
        if ($request['id'] !== $user->id) {
            User::whereId($id)->update([
                'statut' => $request['statut'],
            ]);
            return response()->json([
                'success' => true,
            ]);
        } else {
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function GetPermissions()
    {
        $roles = Auth::user()->roles()->with('permissions')->first();
        $data = [];
        if ($roles) {
            foreach ($roles->permissions as $permission) {
                $item[$permission->name]['slug'] = $permission->name;
                $item[$permission->name]['id'] = $permission->id;

            }
            $data[] = $item;
        }
        return $data[0];

    }

    //------------- GET USER Auth ---------\\

    public function GetInfoProfile(Request $request)
{
    $user = Auth::user()->load('wallet');

    $Sales = Sale::where('user_id', $user->id)->with(['user', 'branch', 'client', 'details'])->get();

    $formattedSales = [];
    foreach ($Sales as $Sale) {
        $item = [
            'id' => $Sale->id,
            'date' => $Sale->date,
            'Ref' => $Sale->Ref,
            'created_by' => $Sale->user->username,
            'statut' => $Sale->statut,
            'shipping_status' => $Sale->shipping_status,
            'discount' => $Sale->discount,
            'shipping' => $Sale->shipping,
            'branch_name' => $Sale->branch->name,
            'client_id' => $Sale->client->id,
            'client_name' => $Sale->client->name,
            'client_email' => $Sale->client->email,
            'client_tele' => $Sale->client->phone,
            'client_code' => $Sale->client->code,
            'client_adr' => $Sale->client->adresse,
            'penalty' => number_format($Sale->penalty, 2, '.', ''),
            'installment' => $Sale->installment,
            'GrandTotal' => number_format($Sale->GrandTotal + $Sale->penalty, 2, '.', ''),
            'paid_amount' => number_format($Sale->paid_amount, 2, '.', ''),
            'due' => number_format(($Sale->GrandTotal + $Sale->penalty) - $Sale->paid_amount, 2, '.', ''),
            'payment_status' => $Sale->payment_statut,
            'payment_frequency' => $Sale->payment_frequency,
            'sale_details' => []
        ];

        foreach ($Sale->details as $detail) {
            $item['sale_details'][] = [
                'product_name' => $detail->product_name,
                'quantity' => $detail->quantity,
                'price' => number_format($detail->price, 2, '.', ''),
                'total' => number_format($detail->total, 2, '.', '')
            ];
        }

        $formattedSales[] = $item;
    }

    return response()->json([
        'success' => true,
        'user' => $user,
        'wallet_balance' => $user->wallet ? $user->wallet->balance : '0.00', // Adjust based on your actual field name
        'sales' => $formattedSales
    ]);
}
        

}
