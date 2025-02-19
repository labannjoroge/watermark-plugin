import React, { useState, useEffect } from 'react';
import { useWatermark } from '../../hooks/useWatermark';
import { Loader, Check } from 'lucide-react';

const BulkWatermark = () => {
  const [images, setImages] = useState([]);
  const [selectedImages, setSelectedImages] = useState(new Set());
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState(null);
  const { bulkWatermark } = useWatermark();

  useEffect(() => {
    fetchImages();
  }, []);

  const fetchImages = async () => {
    try {
      const response = await fetch(`${window.WMData.restUrl}images`, {
        headers: {
          'X-WP-Nonce': window.WMData.nonce
        }
      });
      
      if (!response.ok) throw new Error('Failed to fetch images');
      
      const data = await response.json();
      setImages(data);
    } catch (err) {
      setError('Failed to load images');
    }
  };

  const toggleImage = (id) => {
    const newSelected = new Set(selectedImages);
    if (newSelected.has(id)) {
      newSelected.delete(id);
    } else {
      newSelected.add(id);
    }
    setSelectedImages(newSelected);
  };

  const selectAll = () => {
    setSelectedImages(new Set(images.map(img => img.id)));
  };

  const deselectAll = () => {
    setSelectedImages(new Set());
  };

  const applyWatermarks = async () => {
    if (selectedImages.size === 0) {
      setError('Please select at least one image');
      return;
    }

    setIsProcessing(true);
    setError(null);

    try {
      const results = await bulkWatermark(Array.from(selectedImages));
      alert(`Successfully watermarked ${results.watermarked} images`);
      await fetchImages();
      setSelectedImages(new Set());
    } catch (err) {
      setError(err.message);
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="p-4 bg-white rounded-lg shadow">
      <div className="flex flex-col space-y-4">
        <div className="flex justify-between items-center border-b pb-4">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">Bulk Watermark</h2>
            <p className="text-sm text-gray-500">Select images to apply watermark in bulk</p>
          </div>
          <div className="flex space-x-2">
            <button
              onClick={selectAll}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
            >
              Select All
            </button>
            <button
              onClick={deselectAll}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
            >
              Deselect All
            </button>
            <button
              onClick={applyWatermarks}
              disabled={isProcessing || selectedImages.size === 0}
              className="px-4 py-2 text-sm font-medium text-white bg-black rounded hover:bg-gray-800 disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
              {isProcessing ? (
                <span className="flex items-center">
                  <Loader className="w-4 h-4 mr-2 animate-spin" />
                  Processing...
                </span>
              ) : (
                'Apply Watermarks'
              )}
            </button>
          </div>
        </div>

        {error && (
          <div className="p-4 text-sm text-red-700 bg-red-100 rounded">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {images.map((image) => (
            <div
              key={image.id}
              className="relative cursor-pointer group"
              onClick={() => toggleImage(image.id)}
            >
              <div className="relative aspect-square overflow-hidden rounded border border-gray-200 bg-gray-100">
                <img
                  src={image.thumbnail}
                  alt={image.title}
                  className="w-full h-full object-cover"
                />
                <div className="absolute top-2 left-2">
                  <div className={`w-5 h-5 rounded-sm border ${selectedImages.has(image.id) ? 'bg-black border-black' : 'border-gray-400 bg-white'} flex items-center justify-center`}>
                    {selectedImages.has(image.id) && (
                      <Check className="w-4 h-4 text-white" />
                    )}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default BulkWatermark;