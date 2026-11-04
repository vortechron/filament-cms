<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Access\CmsRoles;
use Vortechron\FilamentCms\Filament\Pages\EditContentTypes;
use Vortechron\FilamentCms\Filament\Pages\EditSettings;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Vortechron\FilamentCms\Filament\Resources\Roles\Pages\ManageRoles;
use Vortechron\FilamentCms\Filament\Resources\Roles\RoleResource;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Filament\Resources\Users\Pages\ManageUsers;
use Vortechron\FilamentCms\Filament\Resources\Users\UserResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Tests\TestCase;
use Workbench\App\Models\User;

class RolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
    }

    private function rolesOn(): void
    {
        Filament::getPanel('admin')->getPlugin(FilamentCmsPlugin::ID)->roles();
        CmsRoles::sync();
    }

    private function signInAs(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::getCurrentPanel()->boot();

        return $user;
    }

    /**
     * @return list<string>
     */
    private function permissionsOf(string $role): array
    {
        return Role::findByName($role, 'web')->permissions()->pluck('name')->sort()->values()->all();
    }

    public function test_sync_makes_the_permissions_and_two_roles_once_and_keeps_the_owners_changes(): void
    {
        CmsRoles::sync();
        CmsRoles::sync();

        $this->assertSame(8, Permission::query()->count());
        $this->assertSame(2, Role::query()->count());
        $this->assertSame(collect(CmsRoles::PERMISSIONS)->sort()->values()->all(), $this->permissionsOf(CmsRoles::SUPER_ADMIN));
        $this->assertSame(['cms.entries', 'cms.forms', 'cms.pages', 'cms.sections', 'cms.settings', 'cms.users'], $this->permissionsOf(CmsRoles::ADMIN));

        Role::findByName(CmsRoles::ADMIN, 'web')->revokePermissionTo('cms.forms');
        Permission::findByName('cms.sections', 'web')->delete();
        CmsRoles::forget();
        $this->assertTrue(CmsRoles::needsSync());
        CmsRoles::sync();

        $this->assertNotContains('cms.forms', $this->permissionsOf(CmsRoles::ADMIN));
        $this->assertContains('cms.sections', $this->permissionsOf(CmsRoles::ADMIN));
        $this->assertContains('cms.sections', $this->permissionsOf(CmsRoles::SUPER_ADMIN));
        $this->assertFalse(CmsRoles::needsSync());
    }

    public function test_the_admin_opens_the_content_screens_and_users_but_not_roles_or_content_types(): void
    {
        $this->rolesOn();
        $admin = $this->signInAs(CmsRoles::ADMIN);

        $this->assertTrue(SitePageResource::canAccess());
        $this->assertTrue(FormSubmissionResource::canAccess());
        $this->assertTrue(EntryResource::canAccess());
        $this->assertTrue(EditSettings::canAccess());
        $this->assertTrue(UserResource::canAccess());
        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(EditContentTypes::canAccess());
        $this->assertFalse(FilamentCmsPlugin::get()->isDeveloper($admin));

        $this->get(RoleResource::getUrl())->assertForbidden();
        $this->get(EditContentTypes::getUrl())->assertForbidden();
        $this->get(UserResource::getUrl())->assertOk()->assertSee('Users');
    }

    public function test_the_super_admin_opens_everything_and_is_the_developer(): void
    {
        $this->rolesOn();
        $super = $this->signInAs(CmsRoles::SUPER_ADMIN);

        foreach ([SitePageResource::class, UserResource::class, RoleResource::class, EditContentTypes::class, EditSettings::class] as $screen) {
            $this->assertTrue($screen::canAccess(), $screen);
        }

        $this->assertTrue(FilamentCmsPlugin::get()->isDeveloper($super));
        $this->get(RoleResource::getUrl())->assertOk()->assertSee(['Super admin', 'Admin']);
    }

    public function test_a_role_with_only_form_submissions_opens_only_that(): void
    {
        $this->rolesOn();
        Role::create(['name' => 'staff', 'guard_name' => 'web'])->givePermissionTo('cms.forms');
        $this->signInAs('staff');

        $this->assertTrue(FormSubmissionResource::canAccess());
        $this->assertFalse(SitePageResource::canAccess());
        $this->assertFalse(EditSettings::canAccess());
        $this->assertFalse(UserResource::canAccess());
        $this->get(SitePageResource::getUrl())->assertForbidden();
    }

    public function test_an_admin_never_sees_or_gives_super_admin(): void
    {
        $this->rolesOn();
        $super = User::factory()->create(['name' => 'Vortechron']);
        $super->assignRole(CmsRoles::SUPER_ADMIN);
        $admin = $this->signInAs(CmsRoles::ADMIN, ['name' => 'Adam']);

        $this->assertSame([CmsRoles::ADMIN => 'Admin'], CmsAccess::assignableRoles());

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$super])
            ->callAction(CreateAction::class, ['name' => 'Siti', 'email' => 'siti@example.com', 'password' => 'a-long-password', 'role' => CmsRoles::SUPER_ADMIN])
            ->assertHasActionErrors(['role']);

        Livewire::test(ManageUsers::class)
            ->callAction(CreateAction::class, ['name' => 'Siti', 'email' => 'siti@example.com', 'password' => 'a-long-password', 'role' => CmsRoles::ADMIN])
            ->assertHasNoActionErrors();

        $siti = User::query()->where('email', 'siti@example.com')->firstOrFail();
        $this->assertTrue($siti->hasRole(CmsRoles::ADMIN));
        $this->assertTrue(Hash::check('a-long-password', $siti->password));
    }

    public function test_nobody_deletes_themselves_or_changes_their_own_role(): void
    {
        $this->rolesOn();
        $admin = $this->signInAs(CmsRoles::ADMIN);
        $other = User::factory()->create();
        $other->assignRole(CmsRoles::ADMIN);

        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table($admin))
            ->assertActionVisible(TestAction::make('delete')->table($other))
            ->callAction(TestAction::make('edit')->table($admin), ['name' => 'Adam baru'])
            ->assertHasNoActionErrors();

        $this->assertSame('Adam baru', $admin->fresh()->name);
        $this->assertSame([CmsRoles::ADMIN], $admin->fresh()->getRoleNames()->all());
    }

    public function test_a_blank_password_keeps_the_old_one(): void
    {
        $this->rolesOn();
        $this->signInAs(CmsRoles::SUPER_ADMIN);
        $staff = User::factory()->create(['password' => 'the-old-password']);
        $staff->assignRole(CmsRoles::ADMIN);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($staff), ['name' => 'Staff', 'password' => '', 'role' => CmsRoles::SUPER_ADMIN])
            ->assertHasNoActionErrors();

        $this->assertTrue(Hash::check('the-old-password', $staff->fresh()->password));
        $this->assertTrue($staff->fresh()->hasRole(CmsRoles::SUPER_ADMIN));
    }

    public function test_the_super_admin_makes_a_role_and_the_built_in_ones_stay(): void
    {
        $this->rolesOn();
        $this->signInAs(CmsRoles::SUPER_ADMIN);
        $forms = Permission::findByName('cms.forms', 'web');

        Livewire::test(ManageRoles::class)
            ->callAction(CreateAction::class, ['name' => 'staff', 'permissions' => [$forms->getKey()]])
            ->assertHasNoActionErrors()
            ->assertActionHidden(TestAction::make('edit')->table(Role::findByName(CmsRoles::SUPER_ADMIN, 'web')))
            ->assertActionHidden(TestAction::make('delete')->table(Role::findByName(CmsRoles::ADMIN, 'web')))
            ->assertActionVisible(TestAction::make('delete')->table(Role::findByName('staff', 'web')));

        $this->assertSame(['cms.forms'], $this->permissionsOf('staff'));
    }

    public function test_with_roles_off_users_and_roles_stay_closed_and_content_types_is_for_developers(): void
    {
        $this->actingAsClient();

        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(EditContentTypes::canAccess());
        $this->assertTrue(SitePageResource::canAccess());

        $this->actingAsDeveloper();

        $this->assertTrue(EditContentTypes::canAccess());
        $this->assertFalse(UserResource::canAccess());
    }
}
