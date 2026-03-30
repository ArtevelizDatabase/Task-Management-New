<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ── Dashboard (home) ─────────────────────────────────────────
$routes->get('/', 'Dashboard::index');
$routes->get('dashboard', 'Dashboard::index');

// ── Auth ──────────────────────────────────────────────────────
$routes->get('auth/login',                  'Auth::login');
$routes->post('auth/login',                 'Auth::doLogin');
$routes->get('auth/logout',                 'Auth::logout');
$routes->post('auth/impersonate/(:num)',     'Auth::impersonate/$1');
$routes->get('auth/stop-impersonation',     'Auth::stopImpersonation');

// ── Tasks ────────────────────────────────────────────────────
$routes->get('tasks',                       'Tasks::index');
$routes->post('tasks/store',                'Tasks::store');
$routes->get('tasks/submissions',           'Tasks::submissions');
$routes->get('tasks/trash',                 'Tasks::trash');
$routes->get('tasks/(:num)',                'Tasks::show/$1');
$routes->get('tasks/(:num)/edit',           'Tasks::edit/$1');
$routes->post('tasks/(:num)/update',        'Tasks::update/$1');
$routes->post('tasks/(:num)/delete',        'Tasks::delete/$1');
$routes->post('tasks/(:num)/restore',       'Tasks::restore/$1');
$routes->post('tasks/(:num)/force-delete',  'Tasks::forceDelete/$1');
$routes->post('tasks/trash/bulk',           'Tasks::bulkTrashAction');
$routes->post('tasks/(:num)/status',        'Tasks::updateStatus/$1');
$routes->post('tasks/(:num)/field-update',  'Tasks::fieldUpdate/$1');
$routes->post('tasks/(:num)/setor',         'Tasks::toggleSetor/$1');
$routes->post('tasks/(:num)/upload-status', 'Tasks::updateUploadStatus/$1');
$routes->get('tasks/(:num)/setor-data',     'Tasks::getSetorData/$1');
$routes->post('tasks/bulk-create',          'Tasks::bulkCreate');
$routes->post('tasks/bulk',                 'Tasks::bulkTasks');
$routes->post('tasks/(:num)/duplicate',     'Tasks::duplicate/$1');
$routes->post('tasks/(:num)/core-update',   'Tasks::updateCore/$1');
$routes->get('vendors',                     fn() => redirect()->to('/accounts?type=vendor'));
$routes->post('vendors/store',              'VendorAccounts::store');
$routes->post('vendors/(:num)/update',      'VendorAccounts::update/$1');
$routes->post('vendors/(:num)/delete',      'VendorAccounts::delete/$1');
$routes->post('vendors/(:num)/target',      'VendorAccounts::setTarget/$1');
$routes->post('vendors/(:num)/allocation',  'VendorAccounts::setAllocation/$1');
$routes->post('vendors/(:num)/rule',        'VendorAccounts::setRule/$1');
$routes->get('accounts',                    'Accounts::index');
$routes->post('accounts/store',             'Accounts::store');
$routes->post('accounts/(:num)/update',     'Accounts::update/$1');
$routes->post('accounts/(:num)/delete',     'Accounts::delete/$1');
$routes->get('projects/monitoring',         'ProjectMonitoring::index');

// ── Clients ───────────────────────────────────────────────────
$routes->get('clients',                     'Clients::index');
$routes->post('clients/store',              'Clients::store');
$routes->get('clients/(:num)',             'Clients::show/$1');
$routes->post('clients/(:num)/update',     'Clients::update/$1');
$routes->post('clients/(:num)/delete',      'Clients::delete/$1');

// ── Projects (per-client) ───────────────────────────────────
$routes->get('projects',                    'Projects::index');
$routes->post('projects/store',             'Projects::store');
$routes->get('projects/(:num)/tasks/(:num)/panel', 'Tasks::showForProjectPanel/$1/$2');
$routes->get('projects/(:num)/tasks/(:num)', 'Tasks::showForProject/$1/$2');
$routes->get('projects/(:num)',             'Projects::show/$1');
$routes->post('projects/(:num)/update',      'Projects::update/$1');
$routes->post('projects/(:num)/delete',      'Projects::delete/$1');

// ── Task extras (comments, attachments, …) ─────────────────
$routes->post('tasks/(:num)/comments',                     'TaskExtras::addComment/$1');
$routes->post('tasks/(:num)/comments/(:num)/delete',       'TaskExtras::deleteComment/$1/$2');
$routes->post('tasks/(:num)/revisions',                    'TaskExtras::addRevision/$1');
$routes->post('tasks/(:num)/revisions/(:num)/status',     'TaskExtras::updateRevisionStatus/$1/$2');
$routes->post('tasks/(:num)/revisions/(:num)/delete',     'TaskExtras::deleteRevision/$1/$2');
$routes->post('tasks/(:num)/attachments',                  'TaskExtras::uploadAttachment/$1');
$routes->get('tasks/(:num)/relation-tasks',            'TaskExtras::relationTaskSearch/$1');
$routes->get('tasks/(:num)/attachments/(:segment)/serve', 'TaskExtras::serveAttachment/$1/$2');
$routes->post('tasks/(:num)/attachments/(:num)/delete',   'TaskExtras::deleteAttachment/$1/$2');
$routes->post('tasks/(:num)/assignees',                   'TaskExtras::addAssignee/$1');
$routes->post('tasks/(:num)/assignees/(:num)/remove',     'TaskExtras::removeAssignee/$1/$2');
$routes->post('tasks/(:num)/relations',                   'TaskExtras::addRelation/$1');
$routes->post('tasks/(:num)/relations/(:num)/delete',     'TaskExtras::deleteRelation/$1/$2');
$routes->post('favorites/toggle',                         'TaskExtras::toggleFavorite');
$routes->get('favorites',                                'TaskExtras::listFavorites');
$routes->get('templates',                                'TaskExtras::listTemplates');
$routes->post('templates/store',                          'TaskExtras::storeTemplate');
$routes->get('templates/(:num)/fields',                    'TaskExtras::templateFields/$1');
$routes->post('templates/(:num)/delete',                 'TaskExtras::deleteTemplate/$1');
$routes->get('search',                                   'TaskExtras::search');

// ── Fields (admin) ───────────────────────────────────────────
$routes->get('fields',                                'Fields::index');
$routes->post('fields/store',                         'Fields::store');
$routes->get('fields/(:num)',                         'Fields::show/$1');
$routes->post('fields/update/(:num)',                 'Fields::update/$1');
$routes->post('fields/delete/(:num)',                 'Fields::delete/$1');
$routes->post('fields/toggle/(:num)',                 'Fields::toggle/$1');
$routes->post('fields/reorder',                       'Fields::reorder');
$routes->post('fields/setting/(:segment)/toggle',     'Fields::settingToggle/$1');

// ── Settings: Upload status pivot config ─────────────────────
$routes->get('settings/upload-config', 'UploadConfig::index');
$routes->post('settings/upload-config/group', 'UploadConfig::storeGroup');
$routes->post('settings/upload-config/group/(:num)/update', 'UploadConfig::updateGroup/$1');
$routes->post('settings/upload-config/group/(:num)/delete', 'UploadConfig::deleteGroup/$1');
$routes->post('settings/upload-config/group/(:num)/toggle', 'UploadConfig::toggleGroup/$1');
$routes->post('settings/upload-config/group/(:num)/platforms', 'UploadConfig::saveGroupPlatforms/$1');
$routes->post('settings/upload-config/group/(:num)/filetypes', 'UploadConfig::saveGroupFileTypes/$1');
$routes->post('settings/upload-config/platform', 'UploadConfig::storePlatform');
$routes->post('settings/upload-config/platform/(:num)/update', 'UploadConfig::updatePlatform/$1');
$routes->post('settings/upload-config/platform/(:num)/delete', 'UploadConfig::deletePlatform/$1');
$routes->post('settings/upload-config/platform/(:num)/toggle', 'UploadConfig::togglePlatform/$1');
$routes->post('settings/upload-config/filetype', 'UploadConfig::storeFileType');
$routes->post('settings/upload-config/filetype/(:num)/update', 'UploadConfig::updateFileType/$1');
$routes->post('settings/upload-config/filetype/(:num)/delete', 'UploadConfig::deleteFileType/$1');
$routes->post('settings/upload-config/filetype/(:num)/toggle', 'UploadConfig::toggleFileType/$1');

// ── Team: Users ──────────────────────────────────────────────
$routes->get('team/users',                            'Team\Users::index');
$routes->get('team/users/directory',                  'Team\Users::directoryJson');
$routes->get('team/users/create',                     'Team\Users::create');
$routes->post('team/users/store',                     'Team\Users::store');
$routes->get('team/users/(:num)/edit',                'Team\Users::edit/$1');
$routes->post('team/users/(:num)/update',             'Team\Users::update/$1');
$routes->post('team/users/(:num)/delete',             'Team\Users::delete/$1');
$routes->post('team/users/(:num)/toggle-status',      'Team\Users::toggleStatus/$1');
$routes->get('team/users/(:num)/activity',            'Team\Users::activity/$1');

// ── Team: Roles ──────────────────────────────────────────────
$routes->get('team/roles',                            'Team\Roles::index');
$routes->post('team/roles/store',                     'Team\Roles::store');
$routes->get('team/roles/(:num)',                     'Team\Roles::show/$1');
$routes->post('team/roles/(:num)/update',             'Team\Roles::update/$1');
$routes->post('team/roles/(:num)/delete',             'Team\Roles::delete/$1');

// ── Team: Teams ──────────────────────────────────────────────
$routes->get('team/teams',                            'Team\Teams::index');
$routes->post('team/teams/store',                     'Team\Teams::store');
$routes->post('team/teams/(:num)/update',             'Team\Teams::update/$1');
$routes->post('team/teams/(:num)/delete',             'Team\Teams::delete/$1');
$routes->post('team/teams/(:num)/add-member',         'Team\Teams::addMember/$1');
$routes->post('team/teams/(:num)/remove-member/(:num)', 'Team\Teams::removeMember/$1/$2');

// ── Profile ──────────────────────────────────────────────────
$routes->get('profile',                               'Profile::index');
$routes->post('profile/update',                       'Profile::update');
$routes->post('profile/change-password',              'Profile::changePassword');

// ── Notifications ─────────────────────────────────────────────
$routes->get('notifications',                         'Notifications::index');
$routes->get('notifications/unread-count',            'Notifications::unreadCount');
$routes->post('notifications/(:num)/read',            'Notifications::markRead/$1');
$routes->post('notifications/(:num)/unread',          'Notifications::markUnread/$1');
$routes->post('notifications/mark-all-read',          'Notifications::markAllRead');
$routes->post('notifications/(:num)/delete',          'Notifications::delete/$1');
$routes->post('notifications/delete-all',             'Notifications::deleteAll');
$routes->post('notifications/delete-read',            'Notifications::deleteRead');
$routes->post('notifications/preferences',            'Notifications::updatePreferences');
