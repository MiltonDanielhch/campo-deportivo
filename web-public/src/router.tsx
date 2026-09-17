import { lazy, Suspense, type ReactNode } from 'react';
import { createBrowserRouter } from 'react-router-dom';
import LayoutPublico from '@/components/layout/LayoutPublico';

const Landing = lazy(() => import('./pages/Landing'));
const Campos = lazy(() => import('./pages/Campos'));
const CampoDetalle = lazy(() => import('./pages/CampoDetalle'));
const Reserva = lazy(() => import('./pages/Reserva'));
const Pago = lazy(() => import('./pages/Pago'));
const Comprobante = lazy(() => import('./pages/Comprobante'));
const ConsultarEstado = lazy(() => import('./pages/ConsultarEstado'));

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
    element: <LayoutPublico />,
    children: [
      { path: '/', element: <SuspenseWrapper><Landing /></SuspenseWrapper> },
      { path: '/campos', element: <SuspenseWrapper><Campos /></SuspenseWrapper> },
      { path: '/campos/:id', element: <SuspenseWrapper><CampoDetalle /></SuspenseWrapper> },
      { path: '/reserva', element: <SuspenseWrapper><Reserva /></SuspenseWrapper> },
      { path: '/pago/:codigo', element: <SuspenseWrapper><Pago /></SuspenseWrapper> },
      { path: '/comprobante/:codigo', element: <SuspenseWrapper><Comprobante /></SuspenseWrapper> },
      { path: '/estado', element: <SuspenseWrapper><ConsultarEstado /></SuspenseWrapper> },
    ],
  },
]);
