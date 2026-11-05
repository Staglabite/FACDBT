<?php

namespace App\Services\HITUAM;

use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use App\Models\HITUAM01\HITUAM_MSHROLE_ACCESS as RoleAccess;
use App\Models\HITUAM01\HITUAM_MSHROLE_SERVICE as RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleService
{
    public function updateAccess($menus, $unmenus, $roleName)
    {
        $user = $this->getAuditUser();
        $now = now();

        $data = array_map(function ($menuName) use ($roleName, $user, $now) {
            return [
                'VROLE' => $roleName,
                'VMENUID' => $menuName,
                'BSTATUS' => true,
                'VCREA' => $user,
                'DCREA' => $now,
                'VMODI' => $user,
                'DMODI' => $now,
                'VDELETE' => null,
                'DDELETE' => null,
            ];
        }, $menus);

        RoleAccess::upsert(
            $data,
            ['VMENUID', 'VROLE'],
            ['BSTATUS', 'VMODI', 'DMODI', 'VDELETE', 'DDELETE']
        );

        RoleAccess::whereIn('VMENUID', $unmenus)
            ->where('VROLE', $roleName)
            ->update([
                'BSTATUS' => false,
                'VMODI' => $user,
                'DMODI' => $now,
                'VDELETE' => $user,
                'DDELETE' => $now,
            ]);
    }

    public function updateServices($services, $unservice, $roleName)
    {
        $user = $this->getAuditUser();

        $data = array_map(function ($servicesName) use ($roleName, $user) {
            $checkService = Service::query()->where('VNAME', $servicesName)->first();
            return [
                'VSERVICE' => $servicesName,
                'VROLE' => $roleName,
                'DBEGINEFF' => $checkService->DBEGINEFF ?? null,
                'DENDEFF' => $checkService->DENDEFF ?? null,
                'VCREA' => $user,
                'VMODI' => $user,
                'DMODI' => now(),
                'DDELETE' => null,
                'VDELETE' => null,
            ];
        }, $services);

        foreach ($unservice as $key => $value) {
            $unservice[$key] = $value;
        }

        RolePermission::upsert(
            $data,
            ['VSERVICE', 'VROLE'],
            ['VMODI', 'DMODI', 'DDELETE', 'VDELETE', 'DBEGINEFF', 'DENDEFF']
        );


        RolePermission::whereIn('VSERVICE', $unservice)
            ->where('VROLE', $roleName)
            ->update([
                'VDELETE' => $user,
                'VMODI' => $user,
                'DMODI' => now(),
            ]);

        RolePermission::whereIn('VSERVICE', $unservice)
            ->where('VROLE', $roleName)
            ->delete();
    }

    public function menuMapping($menus, $roleName)
    {
        $mappedMenu = array_map(function ($menuName) use ($roleName) {
            return [
                'VROLE' => $roleName,
                'VMENUID' => $menuName,
            ];
        }, $menus);

        return $mappedMenu;
    }

    public function serviceMapping($services, $roleName)
    {
        $mappedServices = array_map(function ($servicesName) use ($roleName) {
            return [
                'VROLE' => $roleName,
                'VSERVICE' => $servicesName,
            ];
        }, $services);

        return $mappedServices;
    }

    public function getAllRoles(Request $request)
    {
        $roles = Role::filtered($request)->get();

        return $roles;
    }

    private function getAuditUser(): string
    {
        return Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? Auth::user()?->email ?? 'SYSTEM';
    }
}
