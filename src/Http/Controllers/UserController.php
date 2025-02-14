<?php

namespace Encore\LoginCheckSafe\Http\Controllers;

use Encore\Admin\Facades\Admin;
use Encore\LoginCheckSafe\Actions\Post\LogLoginView;
use Encore\LoginCheckSafe\Actions\Post\LogPassView;
use Encore\LoginCheckSafe\Models\PasswordLogModel;
use Encore\LoginCheckSafe\Rules\AdminPassword;
use Illuminate\Routing\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Route;

class UserController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header(trans('admin.administrator'))
            ->description(trans('admin.list'))
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed   $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header(trans('admin.administrator'))
            ->description(trans('admin.detail'))
            ->body($this->detail($id));
    }

    /**
     * Edit interface.
     *
     * @param mixed   $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header(trans('admin.administrator'))
            ->description(trans('admin.edit'))
            ->body($this->form()->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header(trans('admin.administrator'))
            ->description(trans('admin.create'))
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $userModel = config('admin.database.users_model');

        $grid = new Grid(new $userModel());
        $grid->disableExport();
        $grid->id('ID')->sortable();
        $grid->username(trans('admin.username'));
        $grid->name(trans('admin.name'));
        $grid->roles(trans('admin.roles'))->pluck('name')->label();
        $states = [
            'off' => ['value' => 0, 'text' => trans('admins.disabled'), 'color' => 'default'],
            'on'  => ['value' => 1, 'text' => trans('admins.enabled'), 'color' => 'primary'],
        ];
        $grid->enabled(trans('admins.state'))->switch($states);
        $grid->created_at(trans('admin.created_at'));
        $grid->updated_at(trans('admin.updated_at'));
        $grid->pass_update_at(trans('admins.pass_update_at'));
        $grid->login_at(trans('admins.login_at'));
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
            //添加两个日志链接
            //$actions->add(new LogLoginView());
            //$actions->add(new LogPassView());

        });

        $grid->tools(function (Grid\Tools $tools) {
            $tools->batch(function (Grid\Tools\BatchActions $actions) {
                $actions->disableDelete();
            });
        });

        $grid->filter(function ($filter){
            $filter->like('username',trans('admin.username'));
            $filter->equal('enabled',trans('admins.state'))->radio([
                '' => trans('admin.all'),
                0 => trans('admins.disabled'),
                1 => trans('admins.enabled')
            ]);
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed   $id
     * @return Show
     */
    protected function detail($id)
    {
        $userModel = config('admin.database.users_model');

        $show = new Show($userModel::findOrFail($id));

        $show->id('ID');
        $show->username(trans('admin.username'));
        $show->name(trans('admin.name'));
        $show->roles(trans('admin.roles'))->as(function ($roles) {
            return $roles->pluck('name');
        })->label();
        $show->permissions(trans('admin.permissions'))->as(function ($permission) {
            return $permission->pluck('name');
        })->label();
        $show->created_at(trans('admin.created_at'));
        $show->updated_at(trans('admin.updated_at'));
        $show->pass_update_at(trans('admins.pass_update_at'));
        $show->login_at(trans('admins.login_at'));
        $show->enabled(trans('admins.state'))->using([
            1 => trans('admins.enabled'),
            0 => trans('admins.disabled'),
        ]);

        $show->panel()->tools(function ($tools){
            $tools->disableDelete();
        });

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $userModel = config('admin.database.users_model');
        $permissionModel = config('admin.database.permissions_model');
        $roleModel = config('admin.database.roles_model');
        $connect = config('admin.database.connection');
        $userTable = config('admin.database.users_table');


        $form = new Form(new $userModel());

        $form->tools(function (Form\Tools $tools){
            $tools->disableDelete();
        });
        $form->display('id', 'ID');
        $id = request()->route()->parameter('user');
        if($id){
            $form->display('username', trans('admin.username'));
        }else{
            $rules = 'required|unique:'.$connect.'.'.$userTable;
            if(config('admin.extensions.login-check-safe.username-rules')){
                $rules .= '|'.config('admin.extensions.login-check-safe.username-rules');
            }
            $form->text('username', trans('admin.username'))->rules($rules,config('admin.extensions.login-check-safe.username-rules-msg'));
        }

        $form->text('name', trans('admin.name'))->rules('required');
        $form->image('avatar', trans('admin.avatar'))->uniqueName();

        $form->multipleSelect('roles', trans('admin.roles'))->options($roleModel::all()->pluck('name', 'id'));
        $form->multipleSelect('permissions', trans('admin.permissions'))->options($permissionModel::all()->pluck('name', 'id'));
        $states = [
            'on'  => ['value' => 1, 'text' => trans('admins.enabled'), 'color' => 'success'],
            'off' => ['value' => 0, 'text' => trans('admins.disabled'), 'color' => 'danger'],
        ];
        $form->switch('enabled',trans('admins.state'))->states($states)->default(1);

        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));
        $form->hidden('passwordmd5');
        $form->hidden('pass_update_at');

        if($id){
            $form->divider();
            $form->hidden('password');
            $form->password('new_password', trans('admins.new_password'))->rules(['confirmed', new AdminPassword()]);
            $form->password('new_password_confirmation', trans('admin.password_confirmation'))->default('');
            $form->ignore(['password']);
        }else{
            $form->password('password', trans('admin.password'))->rules(['required', 'confirmed', new AdminPassword()]);
            $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required');
        }


        $form->ignore(['password_confirmation','passwordmd5','pass_update_at','new_password','new_password_confirmation']);
        $form->saving(function (Form $form) {
            //更新前处理
            $new_password = request()->input('new_password')?:$form->password;//新密码
            if ($new_password) {
                $form->passwordmd5 = md5(config('app.key').$new_password);
                $form->password = bcrypt($new_password);
                $form->pass_update_at = now()->toDateTimeString();
            }
        });
        $form->saved(function (Form $form){
            if($form->passwordmd5){
                //查询最近是否使用过
                $passdata = [
                    'user_id' => $form->model()->id,
                    'password' => $form->model()->passwordmd5,
                    'remark' => trans('admins.sys_reset_password',['user'=>Admin::user()->username]),
                ];
                //var_dump($passdata);exit();
                PasswordLogModel::create($passdata);
            }
        });
        //var_dump($form);exit;
        return $form;
    }


}
