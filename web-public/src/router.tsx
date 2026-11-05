import { lazy, Suspense, type ReactNode } from 'react';
import { createBrowserRouter } from 'react-router-dom';
import LayoutPublico from '@/components/layout/LayoutPublico';
import { CiudadanoAuthProvider } from '@/context/CiudadanoAuthProvider';
import RequireCiudadano from '@/components/auth/RequireCiudadano';

const Landing = lazy(() => import('./pages/Landing'));
const Campos = lazy(() => import('./pages/Campos'));
const CampoDetalle = lazy(() => import('./pages/CampoDetalle'));
const Reserva = lazy(() => import('./pages/Reserva'));
const Pago = lazy(() => import('./pages/Pago'));
const Comprobante = lazy(() => import('./pages/Comprobante'));
const ComprobantePiscina = lazy(() => import('./pages/ComprobantePiscina'));
const ConsultarEstado = lazy(() => import('./pages/ConsultarEstado'));
const LoginCiudadano = lazy(() => import('./pages/LoginCiudadano'));
const RegistroCiudadano = lazy(() => import('./pages/RegistroCiudadano'));
const MiAsociacion = lazy(() => import('./pages/MiAsociacion'));

function SuspenseWrapper({ children }: { children: ReactNode }) {
  return (
    <Suspense
      fallback={
        <div className="flex items-center justify-center min-h-screen">
          Cargando...
        </div>
      }
    >
      {children}
    </Suspense>
  );
}

export const router = createBrowserRouter([
  {
    element: (
      <CiudadanoAuthProvider>
        <LayoutPublico />
      </CiudadanoAuthProvider>
    ),
    children: [
      { path: '/', element: <SuspenseWrapper><Landing /></SuspenseWrapper> },
      { path: '/campos', element: <SuspenseWrapper><Campos /></SuspenseWrapper> },
      { path: '/campos/:id', element: <SuspenseWrapper><CampoDetalle /></SuspenseWrapper> },
      { path: '/reserva', element: <SuspenseWrapper><Reserva /></SuspenseWrapper> },
      { path: '/pago/:codigo', element: <SuspenseWrapper><Pago /></SuspenseWrapper> },
      { path: '/comprobante/:codigo', element: <SuspenseWrapper><Comprobante /></SuspenseWrapper> },
      { path: '/comprobante-piscina/:codigo', element: <SuspenseWrapper><ComprobantePiscina /></SuspenseWrapper> },
      { path: '/estado', element: <SuspenseWrapper><ConsultarEstado /></SuspenseWrapper> },
      { path: '/login', element: <SuspenseWrapper><LoginCiudadano /></SuspenseWrapper> },
      { path: '/registro', element: <SuspenseWrapper><RegistroCiudadano /></SuspenseWrapper> },
      {
        path: '/mi-asociacion',
        element: <SuspenseWrapper><RequireCiudadano><MiAsociacion /></RequireCiudadano></SuspenseWrapper>,
      },
    ],
  },
]);
