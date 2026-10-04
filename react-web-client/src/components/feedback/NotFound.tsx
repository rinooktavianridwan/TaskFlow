export function NotFound() {
    return (
        <div className="grid min-h-screen place-items-center bg-gray-50 px-6">
            <div className="w-full max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200">
                <img src="/not_found_icon.png" alt="" className="mx-auto mb-6 h-48 w-auto" />
                <h1 className="text-2xl font-bold text-gray-800">Page not found</h1>
                <p className="mt-2 text-sm text-gray-600">
                    The page you are looking for doesn't exist or has been moved.
                </p>
            </div>
        </div>
    )
}