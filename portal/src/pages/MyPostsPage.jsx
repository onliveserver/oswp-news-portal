import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import { postsApi } from '../api/endpoints';
import { toastPromise } from '../utils/toast';

export default function MyPostsPage() {
  const [posts, setPosts] = useState([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const perPage = 10;

  const fetchPosts = async (p) => {
    setLoading(true);
    try {
      const data = await postsApi.list(`page=${p}&per_page=${perPage}`);
      setPosts(data.posts || []);
      setTotal(data.total || 0);
    } catch {
      setPosts([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPosts(page);
  }, [page]);

  const handleDelete = async (id) => {
    if (!confirm('Delete this post?')) return;
    try {
      await toastPromise(() => postsApi.del(id), {
        loading: 'Deleting post…',
        success: 'Post deleted successfully.',
        error: 'Failed to delete post.',
      });
      fetchPosts(page);
    } catch {
      // handled by toast
    }
  };

  const totalPages = Math.ceil(total / perPage);

  const getBadgeClass = (status) => {
    if (status === 'publish') return 'oswp-badge oswp-badge-publish';
    if (status === 'pending') return 'oswp-badge oswp-badge-pending';
    return 'oswp-badge oswp-badge-draft';
  };

  return (
    <div className="oswp-dashboard">
      <Sidebar />
      <div className="oswp-content-area">
        <div className="oswp-card">
          <div className="oswp-card-header">
            <h2>My Posts</h2>
            <Link to="/posts/new" className="oswp-btn oswp-btn-primary oswp-btn-sm" style={{ width: 'auto' }}>
              New post
            </Link>
          </div>

          {loading ? (
            <div className="oswp-table-wrap">
              <table className="oswp-table">
                <thead>
                  <tr>
                    <th>Thumbnail</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {Array.from({ length: 5 }).map((_, i) => (
                    <tr key={i}>
                      <td><span className="oswp-skeleton" style={{ width: 48, height: 48, display: 'block', borderRadius: 8 }} /></td>
                      <td><span className="oswp-skeleton" style={{ width: '60%', height: 14, display: 'block' }} /></td>
                      <td><span className="oswp-skeleton" style={{ width: 64, height: 20, display: 'block', borderRadius: 999 }} /></td>
                      <td><span className="oswp-skeleton" style={{ width: 80, height: 14, display: 'block' }} /></td>
                      <td><span className="oswp-skeleton" style={{ width: 60, height: 14, display: 'block' }} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : posts.length === 0 ? (
            <div className="oswp-empty">
              <p>You haven't written any posts yet.</p>
              <Link to="/posts/new" className="oswp-btn oswp-btn-primary" style={{ width: 'auto' }}>
                Write your first post
              </Link>
            </div>
          ) : (
            <>
              <div className="oswp-table-wrap">
                <table className="oswp-table">
                  <thead>
                    <tr>
                      <th>Thumbnail</th>
                      <th>Title</th>
                      <th>Status</th>
                      <th>Date</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {posts.map((post) => (
                      <tr key={post.id}>
                        <td>
                          {post.thumbnail_url ? (
                            <img
                              src={post.thumbnail_url}
                              alt={post.title}
                              className="oswp-post-thumb"
                            />
                          ) : (
                            <div className="oswp-post-thumb" />
                          )}
                        </td>
                        <td style={{ fontWeight: 500 }}>{post.title}</td>
                        <td>
                          <span className={getBadgeClass(post.status)}>{post.status}</span>
                        </td>
                        <td style={{ color: 'var(--color-text-secondary)' }}>{post.date}</td>
                        <td>
                          <div className="oswp-actions">
                            {(post.status === 'pending' || post.status === 'draft') && (
                              <Link to={`/posts/${post.id}/edit`}>Edit</Link>
                            )}
                            <button className="delete" onClick={() => handleDelete(post.id)}>
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {totalPages > 1 && (
                <div className="oswp-pagination">
                  <button disabled={page <= 1} onClick={() => setPage(page - 1)}>
                    Prev
                  </button>
                  {Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => (
                    <button key={p} className={p === page ? 'active' : ''} onClick={() => setPage(p)}>
                      {p}
                    </button>
                  ))}
                  <button disabled={page >= totalPages} onClick={() => setPage(page + 1)}>
                    Next
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
