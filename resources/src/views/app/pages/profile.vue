<template>
  <div class="main-content">
    <breadcumb :page="$t('profil')" :folder="$t('Settings')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div class="card user-profile o-hidden mb-30" v-if="!isLoading">
      <div class="header-cover"></div>
      <div class="user-info">
        <img class="profile-picture avatar-lg mb-2" :src="'/images/avatar/'+avatar" alt />
        <p class="m-0 text-24">{{ username }}</p>
      </div>
      <div class="card-body">
        <!--  Profile -->
        <b-modal v-model="showProfileModal" title="Profile">
          <validation-observer ref="Update_Profile">
            <b-form @submit.prevent="Submit_Profile" enctype="multipart/form-data">
              <b-row>
                <!-- First name -->
                <b-col md="6" sm="12">
                  <validation-provider
                    name="Firstname"
                    :rules="{ required: true, min: 4, max: 20 }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Firstname') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="Firstname-feedback"
                        label="Firstname"
                        v-model="user.firstname"
                      ></b-form-input>
                      <b-form-invalid-feedback id="Firstname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Last name -->
                <b-col md="6" sm="12">
                  <validation-provider
                    name="lastname"
                    :rules="{ required: true, min: 4, max: 20 }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('lastname') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="lastname-feedback"
                        label="lastname"
                        v-model="user.lastname"
                      ></b-form-input>
                      <b-form-invalid-feedback id="lastname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Username -->
                <b-col md="6" sm="12">
                  <validation-provider
                    name="username"
                    :rules="{ required: true, min: 4, max: 20 }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('username') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="username-feedback"
                        label="username"
                        v-model="user.username"
                      ></b-form-input>
                      <b-form-invalid-feedback id="username-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Phone -->
                <b-col md="6" sm="12">
                  <validation-provider
                    name="Phone"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Phone') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="Phone-feedback"
                        label="Phone"
                        v-model="user.phone"
                      ></b-form-input>
                      <b-form-invalid-feedback id="Phone-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Email -->
                <b-col md="6" sm="12">
                  <validation-provider
                    name="Email"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Email') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="Email-feedback"
                        label="Email"
                        v-model="user.email"
                      ></b-form-input>
                      <b-form-invalid-feedback id="Email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Avatar -->
                <b-col md="6" sm="12">
                  <validation-provider name="Avatar" ref="Avatar">
                    <b-form-group slot-scope="{ validate, valid, errors }" :label="$t('UserImage')">
                      <input
                        :state="errors[0] ? false : valid ? true : null"
                        :class="{ 'is-invalid': !!errors.length }"
                        @change="onFileSelected"
                        label="Choose Avatar"
                        type="file"
                      />
                      <b-form-invalid-feedback id="Avatar-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- New Password -->
                <b-col md="6">
                  <validation-provider name="New password" :rules="{ min: 6, max: 14 }" v-slot="validationContext">
                    <b-form-group :label="$t('Newpassword')">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="Nawpassword-feedback"
                        :placeholder="$t('LeaveBlank')"
                        label="New password"
                        v-model="user.NewPassword"
                      ></b-form-input>
                      <b-form-invalid-feedback id="Nawpassword-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12" class="mt-3">
                  <b-button variant="primary" type="submit">
                    <i class="i-Yes me-2 font-weight-bold"></i> {{ $t('submit') }}
                  </b-button>
                </b-col>
              </b-row>
            </b-form>
          </validation-observer>
        </b-modal>
        <b-button variant="primary" @click="showProfileModal = true">{{ $t('View & Update Profile') }}</b-button>
        <!-- Sales Information -->
        <div>
          <h3>{{ $t('Sales') }}</h3>
          <div class="grand-total shadow">
            <h4>{{ $t('Wallet Balance') }}: {{ walletBalance }}</h4>
            <b-button variant="success" @click="showTransferModal = true">{{ $t('Transfer') }}</b-button>
          </div>
          <b-table :items="sales" :fields="salesFields">
            <template #cell(actions)="row">
              <b-button variant="primary" @click="viewSale(row.item)">View</b-button>
            </template>
          </b-table>
        </div>
      </div>
    </div>

    <!-- Transfer Modal -->
    <b-modal v-model="showTransferModal" title="Transfer Money" @ok="handleTransfer">
      <validation-observer ref="TransferForm">
        <b-form @submit.stop.prevent="handleTransfer">
          <b-form-group label="Username">
            <validation-provider name="username" :rules="{ required: true }" v-slot="validationContext">
              <b-form-input
                v-model="transferForm.username"
                :state="getValidationState(validationContext)"
                aria-describedby="username-feedback"
              ></b-form-input>
              <b-form-invalid-feedback id="username-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
            </validation-provider>
          </b-form-group>

          <b-form-group label="Total Amount">
            <validation-provider name="totalAmount" :rules="{ required: true, numeric: true }" v-slot="validationContext">
              <b-form-input
                v-model="transferForm.totalAmount"
                :state="getValidationState(validationContext)"
                aria-describedby="totalAmount-feedback"
              ></b-form-input>
              <b-form-invalid-feedback id="totalAmount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
            </validation-provider>
          </b-form-group>
        </b-form>
      </validation-observer>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import { ValidationProvider, ValidationObserver } from 'vee-validate';

export default {
  metaInfo: {
    // if no subcomponents specify a metaInfo.title, this title will be used
    title: "Profile"
  },
  data() {
    return {
      data: new FormData(),
      avatar: "",
      username: "",
      isLoading: true,
      user: {
        id: "",
        firstname: "",
        lastname: "",
        username: "",
        NewPassword: null,
        email: "",
        phone: "",
        avatar: ""
      },
      sales: [],
      walletBalance: 0, // Added walletBalance here
      showProfileModal: false, // Added showProfileModal here
      showTransferModal: false, // Added showTransferModal here
      transferForm: {    // Added transferForm here
        username: '',
        totalAmount: ''
      },
      salesFields: [
        { key: 'date', label: this.$t('date'), sortable: true },
        { key: 'Ref', label: this.$t('Reference'), sortable: true },
        { key: 'client_name', label: this.$t('Customer'), sortable: true },
        { key: 'GrandTotal', label: this.$t('Total'), sortable: true },
        { key: 'paid_amount', label: this.$t('Paid'), sortable: true },
        { key: 'due', label: this.$t('Due'), sortable: true },
      ]
    };
  },

  computed: {
    totalGrandTotal() {
      return this.sales.reduce((total, sale) => {
        return total + parseFloat(sale.GrandTotal);
      }, 0).toFixed(2)
    },
    ...mapGetters(["currentUser"])
  },

  methods: {
    //------------- Submit Update Profile
    Submit_Profile() {
      this.$refs.Update_Profile.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_Profile();
        }
      });
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //------ Validation State fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------------------ Get Profile Info ----------------------\\
    Get_Profile_Info() {
      axios
        .get("Get_user_profile")
        .then(response => {
          this.user = response.data.user;
          this.avatar = this.currentUser.avatar;
          this.sales = response.data.sales || [];
          this.walletBalance = response.data.wallet_balance;
          this.username = this.currentUser.username;
          this.isLoading = false;
        })
        .catch(response => {
          this.isLoading = false;
        });
    },

    //------------------------------ Event Upload Avatar -------------------------------\\
    async onFileSelected(e) {
      const { valid } = await this.$refs.Avatar.validate(e);

      if (valid) {
        this.user.avatar = e.target.files[0];
      } else {
        this.user.avatar = "";
      }
    },

    //------------------ Update Profile ----------------------\\
    Update_Profile() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.data.append("firstname", self.user.firstname);
      self.data.append("lastname", self.user.lastname);
      self.data.append("username", self.user.username);
      self.data.append("email", self.user.email);
      self.data.append("NewPassword", self.user.NewPassword);
      self.data.append("phone", self.user.phone);
      self.data.append("avatar", self.user.avatar);
      self.data.append("_method", "put");

      axios
        .post("update_user_profile/" + self.user.id, self.data)
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Update.TitleProfile"),
            this.$t("Success")
          );
          NProgress.done(), 500;

          setTimeout(() => {
            this.Get_Profile_Info();
          }, 500);
        })
        .catch(error => {
          NProgress.done(), 500;
        });
    },
    showProfileModel() {
      this.showModal = true;
    },
    showTransferModal() {
      this.showModal = true;
    },
    handleTransfer() {
    this.transferProcessing = true; // Show a loading indicator
    NProgress.start();
    NProgress.set(0.1);

    // Validate the form
    this.$refs.TransferForm.validate().then(success => {
        if (success) {
            const payload = {
                username: this.transferForm.username,
                amount: parseFloat(this.transferForm.totalAmount).toFixed(2),
            };

            axios
                .post("transfer", payload)
                .then(response => {
                    this.transferProcessing = false;
                    this.showModal = false; // Close the modal
                    this.makeToast(
                        "success",
                        this.$t("Transfer successful"),
                        this.$t("Success")
                    );
                    // Reset the form
                    this.transferForm.username = '';
                    this.transferForm.totalAmount = '';
                })
                .catch(error => {
                    this.transferProcessing = false;
                    NProgress.done();
                    this.makeToast(
                        "danger",
                        this.$t("Transfer failed"),
                        this.$t("Failed")
                    );
                });
        } else {
            this.transferProcessing = false;
            NProgress.done();
            this.makeToast(
                "danger",
                this.$t("Please fill the form correctly"),
                this.$t("Failed")
            );
        }
    });
  },

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
  }, // END METHODS

  components: {
    ValidationProvider,
    ValidationObserver
  },

  //----------------------------- Created function-------------------
  created() {
    this.Get_Profile_Info();
  }
};
</script>
</script>

<style scoped>
.main-content {
  padding: 20px;
}

.user-info {
  text-align: center;
  padding: 20px 0;
}

.profile-picture {
  border-radius: 50%;
  width: 100px;
  height: 100px;
}

.wallet-section {
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 20px 0;
}

.grand-total {
  text-align: center;
  padding: 20px;
  border: 1px solid #ccc;
  border-radius: 10px;
}

.shadow {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}
</style>