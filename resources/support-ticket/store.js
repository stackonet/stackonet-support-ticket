import Vuex from 'vuex'
import Vue from 'vue'
import axios from 'axios'

Vue.use(Vuex);

export default new Vuex.Store({
    // Same as Vue data
    state: {
        loading: true,
        showSideNav: false,
        snackbar: {},
        pagination: {},
        trashedTickets: {},
        tickets: [],
        filters: [],
        meta_data: [],
        categories: [],
        priorities: [],
        statuses: [],
        agents: [],
        status: 0,
        category: 0,
        priority: 0,
        agent: 0,
        label: 'all',
        city: '',
        search: '',
        currentPage: 1,
        labels: [],
    },

    // Commit + track state changes
    mutations: {
        SET_LOADING_STATUS(state, loading) {
            state.loading = loading;
        },
        SET_SNACKBAR(state, snackbar) {
            state.snackbar = snackbar;
        },
        SET_PAGINATION(state, pagination) {
            state.pagination = pagination;
        },
        SET_TICKETS(state, tickets) {
            state.tickets = tickets;
        },
        SET_FILTERS(state, filters) {
            state.filters = filters;
        },
        SET_META_DATA(state, meta_data) {
            state.meta_data = meta_data;
        },
        SET_CATEGORIES(state, categories) {
            state.categories = categories;
        },
        SET_PRIORITIES(state, priorities) {
            state.priorities = priorities;
        },
        SET_STATUSES(state, statuses) {
            state.statuses = statuses;
        },
        SET_AGENTS(state, agents) {
            state.agents = agents;
        },
        SET_STATUS(state, status) {
            state.status = status;
        },
        SET_CATEGORY(state, category) {
            state.category = category;
        },
        SET_PRIORITY(state, priority) {
            state.priority = priority;
        },
        SET_LABEL(state, label) {
            state.label = label;
        },
        SET_CITY(state, city) {
            state.city = city;
        },
        SET_AGENT(state, agent) {
            state.agent = agent;
        },
        SET_SEARCH(state, search) {
            state.search = search;
        },
        SET_TRASHED_TICKETS(state, trashedTickets) {
            state.trashedTickets = trashedTickets;
        },
        SET_CURRENT_PAGE(state, currentPage) {
            state.currentPage = currentPage;
        },
        SET_SHOW_SIDE_NAVE(state, showSideNav) {
            state.showSideNav = showSideNav;
        },
        SET_TICKETS_LABELS(state, labels) {
            state.labels = labels;
        },
    },

    // Same as Vue methods
    actions: {
        getTickets({commit, state}) {
            commit('SET_LOADING_STATUS', true);
            axios.get(StackonetSupportTicket.restRoot + '/tickets', {
                params: {
                    ticket_status: state.status,
                    ticket_category: state.category,
                    ticket_priority: state.priority,
                    agent: state.agent,
                    page: state.currentPage,
                    label: state.label,
                    city: state.city,
                    search: state.search,
                    per_page: 50,
                }
            }).then(response => {
                let data = response.data.data,
                    filters = data.filters;
                commit('SET_LOADING_STATUS', false);
                commit('SET_TICKETS', data.items);
                commit('SET_PAGINATION', data.pagination);
                commit('SET_META_DATA', data.meta_data);
                commit('SET_TRASHED_TICKETS', data.trash);
                commit('SET_TICKETS_LABELS', data.statuses);
                commit('SET_FILTERS', filters);
            }).catch(error => {
                console.log(error);
                commit('SET_LOADING_STATUS', false);
            });
        },
        getCategories({commit}) {
            commit('SET_LOADING_STATUS', true);
            axios.get(StackonetSupportTicket.restRoot + '/categories').then(response => {
                commit('SET_LOADING_STATUS', false);
                commit('SET_CATEGORIES', response.data.data.items);
            }).catch(error => {
                console.log(error);
                commit('SET_LOADING_STATUS', false);
            });
        },
        getPriorities({commit}) {
            commit('SET_LOADING_STATUS', true);
            axios.get(StackonetSupportTicket.restRoot + '/priorities').then(response => {
                commit('SET_LOADING_STATUS', false);
                commit('SET_PRIORITIES', response.data.data.items);
            }).catch(error => {
                console.log(error);
                commit('SET_LOADING_STATUS', false);
            });
        },
        getStatuses({commit}) {
            commit('SET_LOADING_STATUS', true);
            axios.get(StackonetSupportTicket.restRoot + '/statuses').then(response => {
                commit('SET_LOADING_STATUS', false);
                commit('SET_STATUSES', response.data.data.items);
            }).catch(error => {
                console.log(error);
                commit('SET_LOADING_STATUS', false);
            });
        },
        getAgents({commit}) {
            commit('SET_LOADING_STATUS', true);
            axios.get(StackonetSupportTicket.restRoot + '/agents').then(response => {
                commit('SET_LOADING_STATUS', false);
                commit('SET_AGENTS', response.data.data.items);
            }).catch(error => {
                console.log(error);
                commit('SET_LOADING_STATUS', false);
            });
        },
    },

    // Save as Vue computed property
    getters: {
        display_name() {
            return window.StackonetSupportTicket.display_name;
        },
        user_email() {
            return window.StackonetSupportTicket.user_email;
        }
    },
});