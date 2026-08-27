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

function App() {
  return (
    <>
      <Toaster position="top-right" richColors />
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route
            path="/"
            element={
              <RequireAuth>
                <AppLayout />
              </RequireAuth>
            }
          >
            <Route index element={<Dashboard />} />
            <Route path="campos" element={<Campos />} />
            <Route path="horarios" element={<Horarios />} />
            <Route path="reservas" element={<Reservas />} />
            <Route path="funcionarios" element={<Funcionarios />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </>
  );
}

export default App;
