import axios from 'axios';

const state = {
  user: {}
};

const getters = {
  user: state => state.user
};

const actions = {
  async fetchProfile({ commit }) {
    try {
      const response = await axios.get('/api/profile');
      commit('setUser', response.data);
      return response.data; // Return the data so `then` can be called on it
    } catch (error) {
      console.error('Error fetching profile:', error);
      throw error; // Ensure an error is thrown so `catch` can be used
    }
  },
  async updateProfile({ commit }, user) {
    try {
      const response = await axios.put('/api/profile', user);
      commit('setUser', response.data);
      return response.data;
    } catch (error) {
      console.error('Error updating profile:', error);
      throw error;
    }
  }
};

const mutations = {
  setUser(state, user) {
    state.user = user;
  }
};

export default {
  state,
  getters,
  actions,
  mutations
};
