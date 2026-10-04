import { NavLink } from 'react-router-dom'

type Tab = { to: string; label: string }

export function TabNav({ tabs }: { tabs: Tab[] }) {
    return (
        <nav aria-label="Project sections" className="flex gap-1 border-b border-gray-200">
            {tabs.map((tab) => (
                <NavLink
                    key={tab.to}
                    to={tab.to}
                    className={({ isActive }) =>
                        `-mb-px border-b-2 px-4 py-2 text-sm font-medium transition ${isActive
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