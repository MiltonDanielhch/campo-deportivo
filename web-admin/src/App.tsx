import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from '@/components/ui/sonner';
import { RequireAuth } from '@/components/RequireAuth';
import AppLayout from '@/components/layout/AppLayout';
import Login from '@/pages/auth/Login';
import Dashboard from '@/pages/Dashboard';
import Campos from '@/pages/Campos';
import Horarios from '@/pages/Horarios';
import Reservas from '@/pages/Reservas';
import Funcionarios from '@/pages/Funcionarios';
import TiposCampo from '@/pages/parametricas/TiposCampo';
import CamposDeportivos from '@/pages/parametricas/CamposDeportivos';
import Tarifas from '@/pages/parametricas/Tarifas';

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
            <Route path="parametricas/tipos-campo" element={<TiposCampo />} />
            <Route path="parametricas/tipos-campo" element={<TiposCampo />} />
            <Route path="parametricas/campos" element={<CamposDeportivos />} />
            <Route path="parametricas/campos" element={<CamposDeportivos />} />
            <Route path="parametricas/campos/:campoId/tarifas" element={<Tarifas />} />
            <Route path="campos" element={<Campos />} />
            <Route path="horarios" element={<Horarios />} />
            <Route path="reservas" element={<Reservas />} />
            <Route path="funcionarios" element={<Funcionarios />} />
          </Route>

          {/* Catch-all: cualquier otra ruta redirige al panel */}
          <Route path="*" element={<Navigate to="/panel" replace />} />
        </Routes>
      </BrowserRouter>
    </>
  );
}

export default App;
