import { Outlet, Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { Button } from '@/components/ui/button';

const menuItems = [
  { label: 'Dashboard', path: '/', permiso: null },
  { label: 'Campos', path: '/campos', permiso: 'gestionar-campos' },
  { label: 'Horarios', path: '/horarios', permiso: 'gestionar-horarios' },
  { label: 'Reservas', path: '/reservas', permiso: 'ver-reservas' },
  { label: 'Funcionarios', path: '/funcionarios', permiso: 'gestionar-funcionarios' },
];

export default function AppLayout() {
  const { funcionario, logout, tienePermiso } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="bg-white border-b border-slate-200">
        <div className="container mx-auto px-4 py-3 flex items-center justify-between">
          <h1 className="text-xl font-bold text-slate-800">
            Campos Deportivos — GAD Beni
          </h1>
          <div className="flex items-center gap-4">
            <span className="text-sm text-slate-600">
              {funcionario?.nombre_completo} ({funcionario?.rol.nombre})
            </span>
            <Button variant="outline" size="sm" onClick={handleLogout}>
              Cerrar sesión
            </Button>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-4 py-6">
        <div className="grid grid-cols-12 gap-6">
          <aside className="col-span-2">
            <nav className="space-y-1">
              {menuItems.map((item) => {
                const puedeVer = !item.permiso || tienePermiso(item.permiso);
                return (
                  <Link
                    key={item.path}
                    to={puedeVer ? item.path : '#'}
                    className={`block px-3 py-2 rounded-md text-sm font-medium ${
                      puedeVer
                        ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'
                        : 'text-slate-400 cursor-not-allowed'
                    }`}
                    onClick={(e) => {
                      if (!puedeVer) {
                        e.preventDefault();
                      }
                    }}
                  >
                    {item.label}
                    {!puedeVer && (
                      <span className="ml-2 text-xs text-slate-400">(sin acceso)</span>
                    )}
                  </Link>
                );
              })}
            </nav>
          </aside>

          <main className="col-span-10">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  );
}
