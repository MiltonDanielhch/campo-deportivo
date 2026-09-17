import { Outlet } from 'react-router-dom';
import Header from './Header';
import Footer from '@/components/landing/Footer';

export default function LayoutPublico() {
  return (
    <div className="min-h-screen flex flex-col bg-gray-50">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
