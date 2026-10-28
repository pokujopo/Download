/**
 * hooks/usePosts.js
 *
 * Fetches posts from the real Django API.
 * Replaces the old mock-data version entirely.
 */

import { useState, useEffect, useCallback } from 'react'
import { getPosts } from '@/services/postService'

export function usePosts() {
  const [posts,   setPosts]   = useState([])
  const [loading, setLoading] = useState(true)
  const [error,   setError]   = useState(null)

  const fetchPosts = useCallback(async (params = {}) => {
    setLoading(true)
    setError(null)
    try {
      const data = await getPosts(params)
      // Normalize: API may return array or { results: [] }
      setPosts(Array.isArray(data) ? data : data.results ?? [])
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load posts.')
    } finally {
      setLoading(false)
    }
  }, [])

  // Initial fetch
  useEffect(() => { fetchPosts() }, [fetchPosts])

  const addPost = useCallback((post) => {
    setPosts((prev) => [post, ...prev])
  }, [])

  const stats = {
    total:   posts.length,
    success: posts.filter((p) => p.status === 'success').length,
    failed:  posts.filter((p) => p.status === 'failed').length,
    pending: posts.filter((p) => p.status === 'pending').length,
  }

  return { posts, loading, error, stats, fetchPosts, addPost }
}
