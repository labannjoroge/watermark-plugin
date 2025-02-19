"use client"

import { useState, useEffect } from "react"
import { WatermarkProvider } from "./contexts/WatermarkContext"
import Settings from "./components/Settings"
import BulkWatermark from "./components/BulkWatermark"
import ManageImages from "./components/ManageImages"
import ErrorBoundary from "./ErrorBoundary"

const App = () => {
  const [activeTab, setActiveTab] = useState(() => {
    const appElement = document.getElementById("WM-admin-app")
    return appElement ? appElement.dataset.tab : "settings"
  })

  useEffect(() => {
    const newUrl = new URL(window.location.href)
    newUrl.searchParams.set("tab", activeTab)
    window.history.pushState({}, "", newUrl)
  }, [activeTab])

  const tabs = [
    { id: "settings", label: "Settings" },
    { id: "bulk", label: "Bulk Watermark" },
    { id: "manage", label: "Manage Images" },
  ]

  const renderContent = () => {
    switch (activeTab) {
      case "settings":
        return <Settings />
      case "bulk":
        return <BulkWatermark />
      case "manage":
        return <ManageImages />
      default:
        return <Settings />
    }
  }

  return (
    <ErrorBoundary>
      <WatermarkProvider>
        <div className="min-h-screen bg-gray-50 py-8">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="mb-8">
              <h1 className="text-3xl font-bold text-gray-900">Watermark Manager</h1>
            </div>

            <div className="mb-6 border-b border-gray-200">
              <nav className="-mb-px flex space-x-8">
                {tabs.map((tab) => (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`
                      py-4 px-1 border-b-2 font-medium text-sm
                      ${
                        activeTab === tab.id
                          ? "border-blue-500 text-blue-600"
                          : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                      }
                    `}
                  >
                    {tab.label}
                  </button>
                ))}
              </nav>
            </div>

            <div className="bg-white rounded-lg shadow">{renderContent()}</div>
          </div>
        </div>
      </WatermarkProvider>
    </ErrorBoundary>
  )
}

export default App

