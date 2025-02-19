"use client";

import React, { useState, useEffect } from "react";
import { Loader, MoreVertical, RefreshCw } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useWatermark } from "../../hooks/useWatermark";

const ManageImages = () => {
  const [images, setImages] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [restoringIds, setRestoringIds] = useState(new Set());
  const { restoreOriginal } = useWatermark();

  useEffect(() => {
    fetchWatermarkedImages();
  }, []);

  const fetchWatermarkedImages = async () => {
    try {
      const response = await fetch(
        `${window.WMData.restUrl}watermarked-images`,
        {
          headers: {
            "X-WP-Nonce": window.WMData.nonce,
          },
        }
      );

      if (!response.ok) throw new Error("Failed to fetch watermarked images");

      const data = await response.json();
      setImages(data);
    } catch (err) {
      setError("Failed to load watermarked images");
    } finally {
      setIsLoading(false);
    }
  };

  const handleRestoreOriginal = async (imageId) => {
    setRestoringIds((prev) => new Set([...prev, imageId]));
    setError(null);

    try {
      await restoreOriginal(imageId);
      setImages((prevImages) => prevImages.filter((img) => img.id !== imageId));
    } catch (err) {
      setError(`Failed to restore image: ${err.message}`);
    } finally {
      setRestoringIds((prev) => {
        const newSet = new Set(prev);
        newSet.delete(imageId);
        return newSet;
      });
    }
  };

  const filteredImages = images.filter((image) =>
    image.title.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Loader className="w-8 h-8 animate-spin text-blue-600" />
      </div>
    );
  }

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        {/* Titles */}
        <div>
          <h1 className="text-2xl font-bold mb-1">Manage Watermarked Images</h1>
          <p className="text-gray-600">
            View and manage your watermarked images
          </p>
        </div>

        {/* Search Input */}
        <div className="relative w-96">
          <input
            type="text"
            placeholder="Search images..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full px-4 py-2 border rounded-md"
          />
        </div>
      </div>

      {error && (
        <Alert variant="destructive" className="mb-6">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <div className="bg-white rounded-lg border">
        <table className="w-full">
          <thead>
            <tr className="border-b">
              <th className="text-left p-4">Image</th>
              <th className="text-left p-4">Title</th>
              <th className="text-left p-4">Status</th>
              <th className="text-right p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredImages.map((image) => (
              <tr key={image.id} className="border-b last:border-b-0">
                <td className="p-4">
                  <img
                    src={image.thumbnail || "/placeholder.svg"}
                    alt={image.title}
                    className="w-16 h-16 object-cover rounded"
                  />
                </td>
                <td className="p-4">{image.title}</td>
                <td className="p-4">
                  <span className="px-2 py-1 text-sm rounded-full bg-green-100 text-green-800">
                    Active
                  </span>
                </td>
                <td className="p-4 text-right">
                  <button
                    onClick={() => handleRestoreOriginal(image.id)}
                    disabled={restoringIds.has(image.id)}
                    className="px-4 py-2 text-sm font-medium border rounded-md hover:bg-gray-50"
                  >
                    {restoringIds.has(image.id)
                      ? "Restoring..."
                      : "Restore Original"}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default ManageImages;
