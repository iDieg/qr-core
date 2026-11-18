// Reactive Vue application setup
const { createApp, ref, computed, onMounted } = Vue;

createApp({
    setup() {
        const user = ref(null);
        const csrfToken = ref('');
        const isLogin = ref(true);
        const loading = ref(false);
        const authForm = ref({ nombre: '', email: '', password: '' });
        const qrs = ref([]);
        const searchQuery = ref('');
        const newQR = ref({ alias: '', url: '' });
        const confirmDeleteId = ref(null);
        const deletingId = ref(null);
        const toast = ref({ show: false, message: '', type: 'success' });

        const editModal = ref({
            show: false,
            form: { id_qr: 0, alias: '', url: '', estado_activo: 1 }
        });

        const statsModal = ref({
            show: false,
            alias: '',
            logs: []
        });

        const showToast = (message, type = 'success') => {
            toast.value = { show: true, message, type };
            setTimeout(() => { toast.value.show = false; }, 3500);
        };

        const activeCount = computed(() => qrs.value.filter(q => Number(q.estado_activo) === 1).length);
        const totalScans = computed(() => qrs.value.reduce((acc, q) => acc + Number(q.total_escaneos || 0), 0));
        const filteredQRs = computed(() => {
            const q = searchQuery.value.toLowerCase();
            return qrs.value.filter(item => 
                item.alias_identificador.toLowerCase().includes(q) || 
                item.url_destino.toLowerCase().includes(q)
            );
        });

        // Restore active session and CSRF token
        const initSession = async () => {
            try {
                const res = await fetch('api/index.php?action=session');
                const data = await res.json();
                if (data.success) {
                    csrfToken.value = data.csrf_token || '';
                    if (data.user) {
                        user.value = data.user;
                        await fetchQRs();
                    }
                }
            } catch (e) {
                showToast('Error al inicializar sesión con el servidor', 'error');
            }
        };

        onMounted(() => {
            initSession();
        });

        const secureHeaders = () => ({
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken.value
        });

        const login = async () => {
            loading.value = true;
            try {
                const res = await fetch('api/index.php?action=login', {
                    method: 'POST',
                    headers: secureHeaders(),
                    body: JSON.stringify(authForm.value)
                });
                const data = await res.json();
                if (data.success) {
                    user.value = data.user;
                    csrfToken.value = data.csrf_token;
                    authForm.value = { nombre: '', email: '', password: '' };
                    showToast('Sesión iniciada correctamente');
                    await fetchQRs();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('Error de conexión con el servidor PHP', 'error');
            } finally {
                loading.value = false;
            }
        };

        const register = async () => {
            loading.value = true;
            try {
                const res = await fetch('api/index.php?action=register', {
                    method: 'POST',
                    headers: secureHeaders(),
                    body: JSON.stringify(authForm.value)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    isLogin.value = true;
                    authForm.value = { nombre: '', email: '', password: '' };
                } else {
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('Error al procesar el registro', 'error');
            } finally {
                loading.value = false;
            }
        };

        const logout = async () => {
            try {
                await fetch('api/index.php?action=logout', {
                    method: 'POST',
                    headers: secureHeaders()
                });
            } finally {
                user.value = null;
                qrs.value = [];
                confirmDeleteId.value = null;
                await initSession();
            }
        };

        const fetchQRs = async () => {
            if (!user.value) return;
            try {
                const res = await fetch('api/index.php?action=qrs');
                const result = await res.json();
                if (result.success) {
                    qrs.value = result.data;
                } else if (res.status === 401) {
                    user.value = null;
                }
            } catch (e) {
                showToast('Error al cargar listado de QRs', 'error');
            }
        };

        const createQR = async () => {
            loading.value = true;
            try {
                const res = await fetch('api/index.php?action=qrs', {
                    method: 'POST',
                    headers: secureHeaders(),
                    body: JSON.stringify(newQR.value)
                });
                const result = await res.json();
                if (result.success) {
                    newQR.value = { alias: '', url: '' };
                    showToast(result.message);
                    await fetchQRs();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error al crear código QR', 'error');
            } finally {
                loading.value = false;
            }
        };

        const openEditModal = (qr) => {
            confirmDeleteId.value = null;
            editModal.value = {
                show: true,
                form: {
                    id_qr: qr.id_qr,
                    alias: qr.alias_identificador,
                    url: qr.url_destino,
                    estado_activo: Number(qr.estado_activo)
                }
            };
        };

        const updateQR = async () => {
            try {
                const res = await fetch('api/index.php?action=qrs', {
                    method: 'PUT',
                    headers: secureHeaders(),
                    body: JSON.stringify(editModal.value.form)
                });
                const result = await res.json();
                if (result.success) {
                    editModal.value.show = false;
                    showToast(result.message);
                    await fetchQRs();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error al actualizar el QR', 'error');
            }
        };

        const deleteQR = async (id_qr) => {
            deletingId.value = id_qr;
            try {
                const res = await fetch('api/index.php?action=qrs', {
                    method: 'DELETE',
                    headers: secureHeaders(),
                    body: JSON.stringify({ id_qr })
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message);
                    await fetchQRs();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error al eliminar el QR', 'error');
            } finally {
                deletingId.value = null;
                confirmDeleteId.value = null;
            }
        };

        const openStatsModal = async (qr) => {
            statsModal.value = { show: true, alias: qr.alias_identificador, logs: [] };
            try {
                const res = await fetch(`api/index.php?action=stats&id_qr=${qr.id_qr}`);
                const result = await res.json();
                if (result.success) {
                    statsModal.value.logs = result.data;
                }
            } catch (e) {
                showToast('No se pudo cargar la telemetría', 'error');
            }
        };

        return {
            user, isLogin, loading, authForm, qrs, searchQuery, filteredQRs,
            activeCount, totalScans, newQR, confirmDeleteId, deletingId, toast,
            editModal, statsModal, login, register, logout, createQR,
            openEditModal, updateQR, deleteQR, openStatsModal
        };
    }
}).mount('#app');
