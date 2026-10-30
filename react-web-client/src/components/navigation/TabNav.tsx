import { NavLink } from 'react-router-dom'

type Tab = { to: string; label: string }

export function TabNav({ tabs }: { tabs: Tab[] }) {
    return (
        <nav aria-label="Project sections" className="flex flex-wrap gap-1 border-b border-gray-200">
            {tabs.map((tab) => (
                <NavLink
                    key={tab.to}
                    to={tab.to}
                    className={({ isActive }) =>
                        `-mb-px min-w-[calc(50%-0.25rem)] flex-1 border-b-2 px-2 py-2 text-center text-sm font-medium transition sm:min-w-0 sm:flex-none sm:px-4 ${isActive
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`
                    }
                >
                    {tab.label}
                </NavLink>
            ))}
        </nav>
    )
}