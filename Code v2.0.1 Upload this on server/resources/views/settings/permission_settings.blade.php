@extends('layout')
@section('title')
    <?= get_label('permission_settings', 'Permission settings') ?>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-2 mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('home') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item">
                            <?= get_label('settings', 'Settings') ?>
                        </li>
                        <li class="breadcrumb-item active">
                            <?= get_label('permissions', 'Permissions') ?>
                        </li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="{{ url('roles/create') }}"><button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('create_role', 'Create role') ?>"><i
                            class='bx bx-plus'></i></button></a>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= get_label('id', 'ID') ?></th>
                                <th><?= get_label('role', 'Role') ?></th>
                                <th><?= get_label('permissions', 'Permissions') ?></th>
                                <th><?= get_label('actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td>
                                        {{ $role->id }}
                                    </td>
                                    <td>
                                        <h4 class="text-capitalize fw-bold mb-0">{{ ucfirst($role->name) }}</h4>
                                    </td>
                                    @if ($role->name == 'admin')
                                        <td>
                                            <span
                                                class="badge bg-success"><?= get_label('admin_has_all_permissions', 'Admin has all the permissions') ?></span>
                                        </td>
                                        <td>-</td> <!-- Display dash for actions -->
                                    @else
                                        <?php $permissions = $role->permissions; ?>
                                        @if (count($permissions) != 0)
                                            <td class="permissions-container">
                                                @foreach ($permissions as $permission)
                                                    <span
                                                        class="badge bg-{{ $permission->name == 'access_all_data' ? 'success' : 'primary' }} m-1 rounded p-2 px-3">
                                                        {{ $role->hasPermissionTo($permission) ? str_replace('_', ' ', $permission->name) : '' }}
                                                    </span>
                                                @endforeach
                                            </td>
                                        @else
                                            <td class="align-items-center">
                                                <span>
                                                    <?= get_label('no_permissions_assigned', 'No Permissions Assigned!') ?>
                                                </span>
                                            </td>
                                        @endif
                                        <td class="align-items-center">
                                            <div class="d-flex">
                                                <a href="{{ url('/roles/edit/' . $role->id) }}" class="card-link"><i
                                                        class='bx bx-edit mx-1'></i></a>
                                                @if (!in_array($role->name, ['Client', 'member']))
                                                    <a href="javascript:void(0);" type="button"
                                                        data-id="{{ $role->id }}" data-type="roles" data-reload="true"
                                                        class="card-link delete mx-4"><i
                                                            class='bx bx-trash text-danger mx-1'></i></a>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
