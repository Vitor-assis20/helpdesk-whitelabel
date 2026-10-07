import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TenantSwitcher } from '@/components/tenant-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Inbox, MessageSquareText, Settings, Users } from 'lucide-react';
import AppLogo from './app-logo';

const ticketsItem: NavItem = { title: 'Chamados', url: '/painel/chamados', icon: Inbox };
// Respostas prontas, equipe e configurações: só dono e administradores (o servidor também barra os atendentes).
const macrosItem: NavItem = { title: 'Respostas prontas', url: '/painel/respostas', icon: MessageSquareText };
const teamItem: NavItem = { title: 'Equipe', url: '/painel/equipe', icon: Users };
const settingsItem: NavItem = { title: 'Configurações', url: '/painel/configuracoes', icon: Settings };

export function AppSidebar() {
    const { panel } = usePage<SharedData>().props;
    const mainNavItems = panel && panel.current.role !== 'agent' ? [ticketsItem, macrosItem, teamItem, settingsItem] : [ticketsItem];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/painel/chamados" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <TenantSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
