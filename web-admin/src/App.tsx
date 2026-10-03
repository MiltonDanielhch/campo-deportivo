import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from '@/components/ui/sonner';
import { RequireAuth } from '@/components/RequireAuth';
import AppLayout from '@/components/layout/AppLayout';
import Login from '@/pages/auth/Login';
import Dashboard from '@/pages/Dashboard';
import Reservas from '@/pages/Reservas';
import TiposCampo from '@/pages/parametricas/TiposCampo';
import CamposDeportivos from '@/pages/parametricas/CamposDeportivos';
import Tarifas from '@/pages/parametricas/Tarifas';
import FuncionariosPage from '@/pages/usuarios/Funcionarios';
import Asignaciones from '@/pages/usuarios/Asignaciones';
import MisCampos from '@/pages/ocupacion/MisCampos';
import MapaGlobal from '@/pages/ocupacion/MapaGlobal';
import DashboardReportes from '@/pages/reportes/Dashboard';
import ClientesFrecuentes from '@/pages/reportes/ClientesFrecuentes';

function App() {
  return (
    <>
      <Toaster position="top-right" richColors />
      <BrowserRouter>
        <Routes>
          {/* Pública: login de funcionarios */}
          <Route path="/login" element={<Login />} />

          {/* Protegida: panel administrativo bajo /panel */}
          <Route
            path="/panel"
            element={
              <RequireAuth>
                <AppLayout />
              </RequireAuth>
            }
          >
            <Route index element={<Dashboard />} />

            {/* Ocupación (Módulo 6) */}
            <Route path="ocupacion" element={<MisCampos />} />
            <Route path="ocupacion/mapa" element={<MapaGlobal />} />

            {/* Reportes (Módulo 6) */}
            <Route path="reportes" element={<DashboardReportes />} />
            <Route path="reportes/clientes-frecuentes" element={<ClientesFrecuentes />} />

            {/* Paramétricas (Fase 2.6) */}
            <Route path="parametricas/tipos-campo" element={<TiposCampo />} />
            <Route path="parametricas/campos" element={<CamposDeportivos />} />
            <Route path="parametricas/campos/:campoId/tarifas" element={<Tarifas />} />

            {/* Usuarios (Fase 2.7) */}
            <Route path="funcionarios" element={<FuncionariosPage />} />

            {/* Reservas (Módulo 7) */}
            <Route path="reservas" element={<Reservas />} />

            <Route path="asignaciones" element={<Asignaciones />} />
          </Route>

          {/* Catch-all: cualquier otra ruta redirige al panel */}
          <Route path="*" element={<Navigate to="/panel" replace />} />
        </Routes>
      </BrowserRouter>
    </>
  );
}

export default App;
