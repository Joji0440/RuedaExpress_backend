<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtener datos del dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Datos específicos según el rol del usuario
        if ($user->hasRole('admin')) {
            return $this->getAdminDashboard();
        } elseif ($user->hasRole('manager')) {
            return $this->getManagerDashboard();
        } else {
            return $this->getUserDashboard($user);
        }
    }

    /**
     * Dashboard para administradores
     */
    private function getAdminDashboard()
    {
        // Cachear datos por 5 minutos
        $data = Cache::remember('admin_dashboard', 300, function () {
            return [
                'stats' => [
                    'total_users' => User::count(),
                    'admin_users' => User::role('admin')->count(),
                    'manager_users' => User::role('manager')->count(),
                    'regular_users' => User::role('user')->count(),
                    'users_this_month' => User::whereMonth('created_at', now()->month)
                                            ->whereYear('created_at', now()->year)
                                            ->count(),
                    'users_today' => User::whereDate('created_at', today())->count(),
                ],
                'recent_users' => User::latest()
                                    ->take(5)
                                    ->with('roles')
                                    ->get(['id', 'name', 'email', 'created_at']),
                'user_growth' => $this->getUserGrowthData(),
                'role_distribution' => $this->getRoleDistribution(),
                'system_info' => $this->getSystemInfo()
            ];
        });

        return response()->json([
            'message' => 'Dashboard de administrador obtenido exitosamente',
            'data' => $data
        ]);
    }

    /**
     * Dashboard para managers
     */
    private function getManagerDashboard()
    {
        $data = Cache::remember('manager_dashboard', 300, function () {
            return [
                'stats' => [
                    'total_users' => User::role('user')->count(),
                    'users_this_month' => User::role('user')
                                            ->whereMonth('created_at', now()->month)
                                            ->whereYear('created_at', now()->year)
                                            ->count(),
                    'users_today' => User::role('user')->whereDate('created_at', today())->count(),
                    'active_users' => User::role('user')->whereNull('suspended_at')->count(),
                ],
                'recent_users' => User::role('user')
                                    ->latest()
                                    ->take(5)
                                    ->get(['id', 'name', 'email', 'created_at']),
                'user_activity' => $this->getUserActivityData()
            ];
        });

        return response()->json([
            'message' => 'Dashboard de manager obtenido exitosamente',
            'data' => $data
        ]);
    }

    /**
     * Dashboard para usuarios regulares
     */
    private function getUserDashboard($user)
    {
        $data = [
            'welcome_message' => "¡Bienvenido de vuelta, {$user->name}!",
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'member_since' => $user->created_at->format('d/m/Y'),
                'roles' => $user->getRoleNames(),
                'last_login' => $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Primera vez',
            ],
            'quick_actions' => [
                ['name' => 'Editar Perfil', 'url' => '/profile', 'icon' => 'user'],
                ['name' => 'Configuración', 'url' => '/settings', 'icon' => 'settings'],
                ['name' => 'Ayuda', 'url' => '/help', 'icon' => 'help'],
            ],
            'notifications' => [
                [
                    'id' => 1,
                    'title' => 'Bienvenido al sistema',
                    'message' => 'Gracias por unirte a Mecánica Asistida',
                    'type' => 'info',
                    'created_at' => now()->subDays(1)->format('d/m/Y')
                ]
            ]
        ];

        return response()->json([
            'message' => 'Dashboard de usuario obtenido exitosamente',
            'data' => $data
        ]);
    }

    /**
     * Obtener datos de crecimiento de usuarios
     */
    private function getUserGrowthData()
    {
        $months = [];
        $counts = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');
            $counts[] = User::whereMonth('created_at', $date->month)
                           ->whereYear('created_at', $date->year)
                           ->count();
        }

        return [
            'labels' => $months,
            'data' => $counts
        ];
    }

    /**
     * Obtener distribución de roles
     */
    private function getRoleDistribution()
    {
        return [
            'admin' => User::role('admin')->count(),
            'manager' => User::role('manager')->count(),
            'user' => User::role('user')->count(),
        ];
    }

    /**
     * Obtener información del sistema
     */
    private function getSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_connection' => config('database.default'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Obtener datos de actividad de usuarios
     */
    private function getUserActivityData()
    {
        $days = [];
        $logins = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days[] = $date->format('D');
            
            // Contar usuarios que se crearon ese día como actividad
            $logins[] = User::whereDate('created_at', $date)->count();
        }

        return [
            'labels' => $days,
            'data' => $logins
        ];
    }

    /**
     * Obtener notificaciones del usuario
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        
        // Por ahora, notificaciones estáticas
        $notifications = [
            [
                'id' => 1,
                'title' => 'Sistema actualizado',
                'message' => 'El sistema ha sido actualizado con nuevas funcionalidades',
                'type' => 'info',
                'read' => false,
                'created_at' => now()->subHours(2)->toISOString()
            ],
            [
                'id' => 2,
                'title' => 'Perfil completado',
                'message' => 'Tu perfil ha sido completado exitosamente',
                'type' => 'success',
                'read' => true,
                'created_at' => now()->subDays(1)->toISOString()
            ]
        ];

        return response()->json([
            'message' => 'Notificaciones obtenidas exitosamente',
            'data' => $notifications
        ]);
    }

    /**
     * Marcar notificación como leída
     */
    public function markNotificationAsRead(Request $request, $notificationId)
    {
        // Por ahora, solo retornamos éxito
        return response()->json([
            'message' => 'Notificación marcada como leída'
        ]);
    }
}
