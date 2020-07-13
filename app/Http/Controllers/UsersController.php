<?php

namespace App\Http\Controllers;

use App\User;
use Carbon\Carbon;
use App\MemberRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UsersResource;
use App\Http\Resources\MemberRolesResource;


class UsersController extends Controller
{
    /*public function __construct()
    {
    $this->middleware('auth:api');
    }*/

    public function searchPermission(){
        $id = auth('api')->user()->id;

        $roles = array();
        $roles = (array)DB::table('user_roles as s')
        ->select('s.*')
        ->leftJoin('user_roles as s1', function ($join) {
              $join->on('s.user_id', '=', 's1.user_id')
                   ->whereRaw(DB::raw('s.updated_at < s1.updated_at'));
         })
        ->whereNull('s1.role_id')
        ->get();

        $collection = collect($roles)->values();

        $size = count($collection[0]);
        $collection = json_decode( json_encode($collection), true); // sem esta linha conseguia aceder a $collection[0][$i] mas não conseguia aceder a $collection[0][$i]['user_id'] porque dava erro "Cannot use object of type stdClass as array"
        $user_role = 0;

        for($i=0; $i<$size; $i++){
            //return response()->json($collection[0][$i]['user_id'],402);
            if($collection[0][$i]['user_id'] == $id)
            {
                $user_role = $collection[0][$i]['role_id'];
            }
        }

        $aux = 1;

        if($user_role == 6 || $user_role == 7){
            $aux = 0;
        }else{
            $aux = 1;
        }
        return $aux;
    }

    public function searchPermissionAwardsAndProjects(){
        $id = auth('api')->user()->id;

        return $id;
    }

    public function getSciences()
    {

        $users = User::
            where('science_id', '<>', null) // livro
            ->get();

        $number_of_users = count($users);

        $lista = [];

        for ($i = 0; $i < $number_of_users; $i++) {
            if (!
                (in_array($users[$i]->science_id, $lista))) {
                array_push($lista, $users[$i]->science_id);
            }
        }

        //    $collection = collect($lista)->sortBy('Name')->keyBy('Science_id')->values()->toArray();

        return $lista;
    }

    public function getScienceUserAuthenticated()
    {

        return auth('api')->user()->science_id;
    }

/*
public function getSciences()
{
$users = User::get()->unique('science_id');

$subset = $users->map(function ($user) {
return $user->only(['name', 'science_id']);
});

return $subset;

}
 */

    public function getAll()
    {
        return UsersResource::collection(User::paginate(10));
    }

    public function profile()
    {
        return auth('api')->user();
    }

    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'name' => 'required',
            'password' => 'sometimes|required|min:6',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'academic_degree' => 'required',
            //'role' => 'required',
            'department' => 'required',
            'career' => 'required',
            'institution_name' => 'required|string',
            'science_id' => 'required|string',
        ]);

        $currentPhoto = $user->photo;

        if ($request->photo != $currentPhoto) {
            $name = time() . '.' . explode('/', explode(':', substr
                ($request->photo, 0, strpos($request->photo, ';')))[1])[1];

            \Image::make($request->photo)->save(public_path
                ('img/profile/') . $name);

            $request->merge(['photo' => $name]);

            $userPhoto = public_path('img/profile/') . $currentPhoto;

            if (file_exists($userPhoto)) {
                @unlink($userPhoto);
            }
        }

        if (!empty($request->password)) {
            $request->merge(['password' => Hash::make($request['password'])]);
        }

        $user->update($request->all());

    }

    public function createUser(Request $request)
    {

        $request->validate([
            'name' => 'required',
            'password' => 'required|min:6',
            'email' => 'required|email|unique:users,email',
            'academic_degree' => 'required',
            
            'department' => 'required',
            'career' => 'required',
            'institution_name' => 'required|string',
            'science_id' => 'required|string',
        ]);

        $user = new User();

        $user->name = $request->name;
        $user->password = Hash::make($request['password']);
        $user->email = $request->email;
        //$user->email_verified_at = Carbon::now(); //adicionado para permitir que um user criado consiga fazer login
        $user->institution_name = $request->institution_name;
        $user->academic_degree = $request->academic_degree;
        
        $user->department = $request->department;
        $user->career = $request->career;
        $user->science_id = $request->science_id;
        $user->created_at = Carbon::now();
        $user->updated_at = Carbon::now();

        $user->save();

        $memberRole = new MemberRoles(); 

        $memberRole->user_id = $user->id;
        $memberRole->role_id = 5;

        $memberRole->save();

        $user->sendEmailVerificationNotification();

        return new UsersResource($user);
    }

    public function deleteUser($id)
    {

        $user = User::findOrFail($id);
        
        $memberRole = MemberRoles::where('user_id', $id);
 
        $memberRole->delete();

        $user->delete();

        return new UsersResource($user);
    }

    public function editUser(Request $request, $id)
    {

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'password' => 'sometimes|required|min:6',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'academic_degree' => 'required',
            //'role' => 'required',
            'department' => 'required',
            'career' => 'required',
            'institution_name' => 'required|string',
            'science_id' => 'required|string',
        ]);

        $user->update($request->all());
    }

    public function getUser($id)
    {
        return new UsersResource(User::find($id));
    }

    public function availableUsers()
    {

        $usersMembers = User::join('members_scientific_committees',
            'users.id', '=', 'members_scientific_committees.user_id')
            ->select('users.*')
            ->get();

        $users = User::all();

        return $users->diff($usersMembers);

    }

    public function availableUsersOnProject()
    {

        $usersResearchers = User::join('project_researchers',
            'users.id', '=', 'project_researchers.user_id')
            ->select('users.*')
            ->get();

        $users = User::all();

        return $users->diff($usersResearchers);

    }

    public function userRoles()
    {

        $users = User::join('user_roles',
            'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->select('users.*', 'roles.name')
            ->get();

        return UsersResource::collection($users);
    }

    public function alterIsActive($id)
    {
        $user = User::findOrFail($id);
        $user->isActive = !$user->isActive;
        $user->save();
        return new UsersResource($user);
    }

    public function search()
    {
        if ($search = \Request::get('q')) {
            $users = User::where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%$search%")
                    ->orWhere('email', 'LIKE', "%$search%")
                    ->orWhere('institution_name', 'LIKE', "%$search%")
                    ->orWhere('academic_degree', 'LIKE', "%$search%")
                    ->orWhere('science_id', 'LIKE', "%$search%")
                    ->orWhere('department', 'LIKE', "%$search%")
                    ->orWhere('career', 'LIKE', "%$search%");
            })->get();
        } else {
            $users = User::all();
        }

        return $users;
    }

    public function promote($id) {

        $user = User::findOrFail($id);

        $user->isAdmin = 1;

        $user->save();

        return new UsersResource($user);
    }

    public function demote($id) {

        $user = User::findOrFail($id);

        $user->isAdmin = 0;

        $user->save();

        return new UsersResource($user);
    }

}
