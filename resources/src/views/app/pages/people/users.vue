<template>
  <div class="main-content">
    <breadcumb :page="$t('StaffManagement')" :folder="$t('Users')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="users"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        enabled: true,
        placeholder: $t('Search_this_table'),
      }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Users_PDF()" size="sm" variant="outline-success m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="users"
              :columns="columns"
              :file-name="'users'"
              :file-type="'xlsx'"
              :sheet-name="'users'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
          <b-button
            @click="New_User()"
            size="sm"
            variant="btn btn-primary btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('users_add')"
          >
            <i class="i-Add"></i>
            {{$t('Add')}}
          </b-button>
        </div>


        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a
              @click="Edit_User(props.row)"
              v-if="currentUserPermissions && currentUserPermissions.includes('users_edit')"
              title="Edit"
              class="cursor-pointer"
              v-b-tooltip.hover
            >
              <i class="i-Edit text-25 text-success"></i>
            </a>
          </span>

          <div v-else-if="props.column.field == 'statut'">
            <label class="switch switch-primary mr-3">
              <input @change="isChecked(props.row)" type="checkbox" v-model="props.row.statut">
              <span class="slider"></span>
            </label>
          </div>
            <span v-else-if="props.column.field == 'avatar'">
            <b-img
                thumbnail
                height="50"
                width="50"
                fluid
                :src="getAvatarUrl(props.row.avatar)"
                alt="User Avatar"
            ></b-img>
          </span>

            <span v-else-if="props.column.field == 'house'">
            <b-img
                thumbnail
                height="50"
                width="50"
                fluid
                :src="getAvatarUrl(props.row.house)"
                alt="User House Image"
            ></b-img>
          </span>
            <span v-else-if="props.column.field == 'g1avatar'">
            <b-img
                thumbnail
                height="50"
                width="50"
                fluid
                :src="getAvatarUrl(props.row.g1avatar)"
                alt="Guarantor 1 Avatar"
            ></b-img>
          </span>

            <span v-else-if="props.column.field == 'g2avatar'">
            <b-img
                thumbnail
                height="50"
                width="50"
                fluid
                :src="getAvatarUrl(props.row.g2avatar)"
                alt="Guarantor 2 Avatar"
            ></b-img>
          </span>
        </template>
      </vue-good-table>
    </div>

    <!-- Multiple Filters  -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Name user  -->
          <b-col md="12">
            <b-form-group :label="$t('username')">
              <b-form-input label="Code" :placeholder="$t('username')" v-model="Filter_Name"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- User Phone -->
          <b-col md="12">
            <b-form-group :label="$t('Phone')">
              <b-form-input label="Phone" :placeholder="$t('SearchByPhone')" v-model="Filter_Phone"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- User Email  -->
          <b-col md="12">
            <b-form-group :label="$t('Email')">
              <b-form-input label="Email" :placeholder="$t('SearchByEmail')" v-model="Filter_Email"></b-form-input>
            </b-form-group>
          </b-col>
          <!-- Status  -->
          <b-col md="12">
            <b-form-group :label="$t('Status')">
              <v-select
                v-model="Filter_status"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                        [
                           {label: 'Actif', value: '1'},
                           {label: 'Inactif', value: '0'}
                        ]"
              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button @click="Get_Users(serverParams.page)" variant="primary m-1" size="sm" block>
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger m-1" size="sm" block>
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <!-- Add & Edit User -->
    <validation-observer ref="Create_User">
      <b-modal hide-footer size="lg" id="New_User" :title="editmode?$t('Edit'):$t('Add')">
        <b-form @submit.prevent="Submit_User" enctype="multipart/form-data">
          <b-row>
            <!-- First name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Firstname"
                :rules="{ required: true , min:3 , max:30}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Firstname') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Firstname-feedback"
                    label="Firstname"
                    v-model="user.firstname"
                    :placeholder="$t('Firstname')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Firstname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Last name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="lastname"
                :rules="{ required: true , min:3 , max:30}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('lastname') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="lastname-feedback"
                    label="lastname"
                    v-model="user.lastname"
                    :placeholder="$t('lastname')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="lastname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Username -->
            <b-col md="6" sm="12">
              <validation-provider
                name="username"
                :rules="{ required: true , min:3 , max:30}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('username') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="username-feedback"
                    label="username"
                    v-model="user.username"
                    :placeholder="$t('username')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="username-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Phone -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Phone"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Phone') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Phone-feedback"
                    label="Phone"
                    v-model="user.phone"
                    :placeholder="$t('Phone')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Phone-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Email -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Email"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Email') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Email-feedback"
                    label="Email"
                    v-model="user.email"
                    :placeholder="$t('Email')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  <b-alert
                    show
                    variant="danger"
                    class="error mt-1"
                    v-if="email_exist !=''"
                  >{{email_exist}}</b-alert>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- password -->
            <b-col md="6" sm="12" v-if="!editmode">
              <validation-provider
                name="password"
                :rules="{ required: true , min:6 , max:14}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('password') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="password-feedback"
                    label="password"
                    type="password"
                    v-model="user.password"
                    :placeholder="$t('password')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="password-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
              <b-col md="12" sm="12">
                  <b-form-group :label="$t('Adress')">
                  <textarea
                      label="Adress"
                      class="form-control"
                      rows="4"
                      v-model="user.adresse"
                      :placeholder="$t('Address')"
                  ></textarea>
                  </b-form-group>
              </b-col>


              <!--            Bank Name-->
              <b-col md="6" sm="12">
                  <validation-provider
                      name="Bank"
                      :rules="{ required: true}"
                  >
                      <b-form-group :label="$t('Bank') + ' ' + '*'">
                          <b-form-input
                              label="Bank"
                              v-model="user.bank"
                              :placeholder="$t('Bank Name')"
                          ></b-form-input>
                      </b-form-group>
                  </validation-provider>
              </b-col>

<!--              Account Number-->

              <b-col md="6" sm="12">
                  <validation-provider
                      name="Account Number"
                      :rules="{ required: true}"
                  >
                      <b-form-group :label="$t('Account Number') + ' ' + '*'">
                          <b-form-input
                              label="Account"
                              v-model="user.account_number"
                              :placeholder="$t('Account Number')"
                          ></b-form-input>
                      </b-form-group>
                  </validation-provider>
              </b-col>





              <!-- role -->
            <b-col md="6" sm="12" class="mb-3">
              <validation-provider name="role" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('RoleName') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="user.role_id"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="roles.map(roles => ({label: roles.name, value: roles.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Avatar -->
            <b-col md="6" sm="12" class="mb-3">
              <validation-provider name="Avatar" ref="Avatar" >
                <b-form-group slot-scope="{validate, valid, errors }" :label="$t('UserImage')">
                  <input
                    :state="errors[0] ? false : (valid ? true : null)"
                    :class="{'is-invalid': !!errors.length}"
                    @change="onFileSelected"
                    label="Choose Avatar"
                    type="file"
                  >
                  <b-form-invalid-feedback id="Avatar-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

<!--              house Image Link-->
              <b-col md="6" sm="12" class="mb-3">
                  <validation-provider name="house" ref="house" >
                      <b-form-group slot-scope="{validate, valid, errors }" :label="$t('Home Image')">
                          <input
                              :state="errors[0] ? false : (valid ? true : null)"
                              :class="{'is-invalid': !!errors.length}"
                              @change="onHFileSelected"
                              label="Choose Image"
                              type="file"
                          >
                          <b-form-invalid-feedback id="Avatar-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                  </validation-provider>
              </b-col>


              <!-- New Password -->
            <b-col md="6" v-if="editmode" class="mb-3">
              <validation-provider
                name="New password"
                :rules="{min:6 , max:14}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Newpassword')">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Nawpassword-feedback"
                    :placeholder="$t('LeaveBlank')"
                    label="New password"
                    v-model="user.NewPassword"
                  ></b-form-input>
                  <b-form-invalid-feedback
                    id="Nawpassword-feedback"
                  >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- assigned_branches -->
            <b-col md="4" sm="4">
              <h5>{{$t('Assigned_Branch')}}</h5>
            </b-col>

            <b-col md="8" sm="8">
              <label class="checkbox checkbox-primary mb-3"><input type="checkbox" v-model="user.is_all_branches"><h5>{{$t('All_Branches')}} <i v-b-tooltip.hover.bottom title="If 'All branches' Selected , User Can access all data for the selected branches" class="text-info font-weight-bold i-Speach-BubbleAsking"></i></h5><span class="checkmark"></span></label>

               <b-form-group class="mt-2" :label="$t('Some_Branch')">
                  <v-select
                    multiple
                    v-model="assigned_branches"
                    @input="Selected_branch"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="branches.map(branches => ({label: branches.name, value: branches.id}))"
                  />
                </b-form-group>
            </b-col>

<!--              Guarantor -->
              <!-- Name -->
              <b-col md="6" sm="12">
                  <b-form-group :label="$t('FullName')">
                      <b-form-input
                          label="FullName"
                          v-model="user.nameg1"
                          :placeholder="$t('Fullname')"
                      ></b-form-input>
                  </b-form-group>
              </b-col>

              <!--  Phone -->
              <b-col md="6" sm="12">
                  <b-form-group :label="$t('Phone G1')">
                      <b-form-input
                          label="PhoneG1"
                          v-model="user.phoneg1"
                          :placeholder="$t('Phone')"
                      ></b-form-input>
                  </b-form-group>
              </b-col>

              <b-col md="6" sm="12">
                  <b-form-group :label="$t('Google_Address G1')">
                      <b-form-input
                          label="Tax Number"
                          v-model="user.houseg1"
                          :placeholder="$t('Google_Address')"
                      ></b-form-input>
                  </b-form-group>
              </b-col>

              <!-- Avatar -->
              <b-col md="6" sm="12" class="mb-3">
                  <validation-provider name="G1Avatar" ref="G1Avatar" >
                      <b-form-group slot-scope="{validate, valid, errors }" :label="$t('Guarantor1')">
                          <input
                              :state="errors[0] ? false : (valid ? true : null)"
                              :class="{'is-invalid': !!errors.length}"
                              @change="onGFileSelected"
                              label="Choose Avatar"
                              type="file"
                          >
                          <b-form-invalid-feedback id="Avatar-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                  </validation-provider>
              </b-col>

              <!-- Avatar -->
              <b-col md="6" sm="12" class="mb-3">
                  <validation-provider name="G2Avatar" ref="G2Avatar" >
                      <b-form-group slot-scope="{validate, valid, errors }" :label="$t('Guarantor2')">
                          <input
                              :state="errors[0] ? false : (valid ? true : null)"
                              :class="{'is-invalid': !!errors.length}"
                              @change="onG2FileSelected"
                              label="Choose Avatar"
                              type="file"
                          >
                          <b-form-invalid-feedback id="Avatar-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                  </validation-provider>
              </b-col>

              <!-- Name -->
              <b-col md="6" sm="12">
                  <b-form-group :label="$t('FullName')">
                      <b-form-input
                          label="FullName"
                          v-model="user.nameg2"
                          :placeholder="$t('Fullname')"
                      ></b-form-input>
                  </b-form-group>
              </b-col>

              <!--  Phone -->
              <b-col md="6" sm="12">
                  <b-form-group :label="$t('Phone G2')">
                      <b-form-input
                          label="PhoneG1"
                          v-model="user.phoneg2"
                          :placeholder="$t('Phone')"
                      ></b-form-input>
                  </b-form-group>
              </b-col>

              <b-col md="6" sm="12">
                  <b-form-group :label="$t('Google_Address G2')">
                      <b-form-input
                          label="Tax Number"
                          v-model="user.houseg2"
                          :placeholder="$t('Google_Address')"
                      ></b-form-input>
                  </b-form-group>
              </b-col>





              <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit"  :disabled="SubmitProcessing"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
                  <div v-once class="typo__p" v-if="SubmitProcessing">
                    <div class="spinner sm spinner-primary mt-3"></div>
                  </div>
            </b-col>

          </b-row>
        </b-form>
      </b-modal>

    </validation-observer>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: {
    title: "Users"
  },
  data() {
    return {
      editmode: false,
      isLoading: true,
      SubmitProcessing:false,
      email_exist:"",
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      totalRows: "",
      search: "",
      limit: "10",
      Filter_Name: "",
      Filter_Email: "",
      Filter_status: "",
      Filter_Phone: "",
      permissions: {},
      users: [],
      roles: [],
      branches: [],
      data: new FormData(),
      user: {
        firstname: "",
        lastname: "",
        username: "",
        password: "",
        NewPassword: null,
        email: "",
        phone: "",
        statut: "",
        role_id: "",
        avatar: "",
        g1avatar: "",
          g2avatar: "",
          house: "",
          is_all_branches:1,
        nameg1: "",
        phoneg1: "",
        houseg1: "",
        nameg2: "",
        phoneg2: "",
        houseg2: "",
        bank: "",
        account_number: "",
          adresse: "",

      },
      assigned_branches:[],
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions"]),
    columns() {
      return [
          {
              key: "avatar",
              label: this.$t("image"),
              field: "avatar",
              type: "image",
              html: true,
              tdClass: "text-left",
              thClass: "text-left"
          },
        {
          label: this.$t("Firstname"),
          field: "firstname",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("lastname"),
          field: "lastname",
          tdClass: "text-left",
          thClass: "text-left"
        },

        {
          label: this.$t("username"),
          field: "username",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Email"),
          field: "email",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Phone"),
          field: "phone",
          tdClass: "text-left",
          thClass: "text-left"
        },
          {
              label: this.$t("Address"),
              field: "address",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              key: "house",
              label: this.$t("Home Image"),
              field: "house",
              type: "image",
              html: true,
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Bank"),
              field: "bank",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Acc. No."),
              field: "account_number",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Role"),
              field: "role_id",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Branch"),
              field: "is_all_branches",
              tdClass: "text-left",
              thClass: "text-left"
          },
        {
          label: this.$t("Status"),
          field: "statut",
          html: true,
          sortable: false,
          tdClass: "text-center",
          thClass: "text-center"
        },

        {
          label: this.$t("Action"),
          field: "actions",
          html: true,
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false
        },
          {
              label: this.$t("Guarantor1"),
              field: "Guarantor1_name",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              key: "g1avatar",
              label: this.$t("image"),
              field: "g1avatar",
              type: "image",
              html: true,
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Guarantor 1 phone"),
              field: "Guarantor1_phone",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Guarantor 1 Address"),
              field: "Guarantor1_house",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Guarantor2"),
              field: "Guarantor2_name",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              key: "g2avatar",
              label: this.$t("image"),
              field: "g2avatar",
              type: "image",
              html: true,
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Guarantor 2 phone"),
              field: "Guarantor2_phone",
              tdClass: "text-left",
              thClass: "text-left"
          },
          {
              label: this.$t("Guarantor 2 Address"),
              field: "Guarantor2_house",
              tdClass: "text-left",
              thClass: "text-left"
          },
      ];
    }
  },

  methods: {

      getAvatarUrl(avatar) {
          return `/images/avatar/${avatar || 'no-image.png'}`;
      },


    //------------- Submit Validation Create & Edit User
    Submit_User() {
      this.$refs.Create_User.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          if (!this.editmode) {
             console.log('hello');
            this.Create_User();
          } else {
            this.Update_User();
          }
        }
      });
    },

    //------ update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Users(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Users(1);
      }
    },

    //------ Event Sort Change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Users(this.serverParams.page);
    },

    //------ Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Users(this.serverParams.page);
    },

    //------ Event Validation State
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_Name = "";
      this.Filter_status = "";
      this.Filter_Phone = "";
      this.Filter_Email = "";
      this.Get_Users(this.serverParams.page);
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    Selected_branch(value) {
          if (!value.length) {
              this.assigned_branches = [];
          }
      },

    //------ Checked Status User
    isChecked(user) {
      axios
        .put("users_switch_activated/" + user.id, {
          statut: user.statut,
          id: user.id
        })
        .then(response => {
          if (response.data.success) {
            if (user.statut) {
              user.statut = 1;
              this.makeToast(
                "success",
                this.$t("ActivateUser"),
                this.$t("Success")
              );
            } else {
              user.statut = 0;
              this.makeToast(
                "success",
                this.$t("DisActivateUser"),
                this.$t("Success")
              );
            }
          } else {
            user.statut = 1;
            this.makeToast(
              "warning",
              this.$t("Delete.Therewassomethingwronge"),
              this.$t("Warning")
            );
          }
        })
        .catch(error => {
          user.statut = 1;
          this.makeToast(
            "warning",
            this.$t("Delete.Therewassomethingwronge"),
            this.$t("Warning")
          );
        });
    },

    //--------------------------- Users PDF ---------------------------\\
    Users_PDF() {
      var self = this;

      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "First Name", dataKey: "firstname" },
        { title: "Last Name", dataKey: "lastname" },
        { title: "Username", dataKey: "username" },
        { title: "Email", dataKey: "email" },
        { title: "Phone", dataKey: "phone" },
        { title: "Guarantor 1,", dataKey: "nameg1"},
        { title: "Guarantor 1 Phone", dataKey: "phone1"},
        { title: "Guarantor 1 Address", dataKey: "houseg1"},
        { title: "Guarantor 2", dataKey: "nameg2"},
        { title: "Guarantor 2 Phone", dataKey: "phoneg2"},
        { title: "Guarantor 2 House", dataKey: "houseg2"}

      ];
      pdf.autoTable(columns, self.users);
      pdf.text("User List", 40, 25);
      pdf.save("User_List.pdf");
    },


    // Simply replaces null values with strings=''
    setToStrings() {
      if (this.Filter_status === null) {
        this.Filter_status = "";
      }
    },

    //----------------------------------- Get All Users  ---------------------------\\
    Get_Users(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "users?page=" +
            page +
            "&name=" +
            this.Filter_Name +
            "&statut=" +
            this.Filter_status +
            "&phone=" +
            this.Filter_Phone +
            "&email=" +
            this.Filter_Email +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.users = response.data.users;
          this.roles = response.data.roles;
          this.branches = response.data.branches;
          this.totalRows = response.data.totalRows;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //------------------------------ Show Modal (Create User) -------------------------------\\
    New_User() {
      this.reset_Form();
      this.editmode = false;
      this.$bvModal.show("New_User");
    },

    //------------------------------ Show Modal (Update User) -------------------------------\\
    Edit_User(user) {
      this.Get_Users(this.serverParams.page);
      this.reset_Form();
      this.Get_Data_Edit(user.id);
      this.user = user;
      this.user.NewPassword = null;
      this.editmode = true;
      this.$bvModal.show("New_User");
    },

    //---------------------- Get_Data_Edit  ------------------------------\\
      Get_Data_Edit(id) {
        axios
            .get("/users/"+id+"/edit")
            .then(response => {
                this.assigned_branches   = response.data.assigned_branches;
            })
            .catch(error => {
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

      async onHFileSelected(e) {
          const { valid } = await this.$refs.house.validate(e);

          if (valid) {
              this.user.house = e.target.files[0];
          } else {
              this.user.house = "";
          }
      },
      //------------------------------ Event Upload Guarantor Avatar -------------------------------\\
      async onGFileSelected(e) {
          const { valid } = await this.$refs.G1Avatar.validate(e);

          if (valid) {
              this.user.g1avatar = e.target.files[0];
          } else {
              this.user.g1avatar = "";
          }
      },
      //------------------------------ Event Upload Guarantor Avatar -------------------------------\\
      async onG2FileSelected(e) {
          const { valid } = await this.$refs.G2Avatar.validate(e);

          if (valid) {
              this.user.g2avatar = e.target.files[0];
          } else {
              this.user.g2avatar = "";
          }
      },
    //------------------------ Create User ---------------------------\\
    Create_User() {
      var self = this;
      self.SubmitProcessing = true;
      self.data.append("firstname", self.user.firstname);
      self.data.append("lastname", self.user.lastname);
      self.data.append("username", self.user.username);
      self.data.append("email", self.user.email);
      self.data.append("password", self.user.password);
      self.data.append("phone", self.user.phone);
      self.data.append("role", self.user.role_id);
      self.data.append("is_all_branches", self.user.is_all_branches);
      self.data.append("avatar", self.user.avatar);
      self.data.append("g1avatar", self.user.g1avatar);
      self.data.append("g2avatar", self.user.g2avatar);
      self.data.append("house", self.user.house);
      self.data.append("nameg1", self.user.nameg1);
      self.data.append("houseg1", self.user.houseg1);
      self.data.append("houseg2", self.user.houseg2);
      self.data.append("nameg2", self.user.nameg2);
      self.data.append("phoneg1", self.user.phoneg1);
      self.data.append("phoneg2", self.user.phoneg2);
      self.data.append("bank", self.user.bank);
      self.data.append("account_number", self.user.account_number);
      self.data.append("adresse", self.user.adresse);


      if (self.assigned_branches.length) {
        for (var i = 0; i < self.assigned_branches.length; i++) {
          self.data.append("assigned_to[" + i + "]", self.assigned_branches[i]);
        }
      }else{
        self.data.append("assigned_to", []);
      }

      axios
        .post("users", self.data)
        .then(response => {
          self.SubmitProcessing = false;
          Fire.$emit("Event_User");

          this.makeToast(
            "success",
            this.$t("Create.TitleUser"),
            this.$t("Success")
          );
        })
        .catch(error => {
          self.SubmitProcessing = false;
          if (error.errors.email.length > 0) {
            self.email_exist = error.errors.email[0];
          }
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //----------------------- Update User ---------------------------\\
    Update_User() {
      var self = this;
      self.SubmitProcessing = true;
      self.data.append("firstname", self.user.firstname);
      self.data.append("lastname", self.user.lastname);
      self.data.append("username", self.user.username);
      self.data.append("email", self.user.email);
      self.data.append("NewPassword", self.user.NewPassword);
      self.data.append("phone", self.user.phone);
      self.data.append("role", self.user.role_id);
      self.data.append("statut", self.user.statut);
      self.data.append("is_all_branches", self.user.is_all_branches);
      self.data.append("avatar", self.user.avatar);

       // append array assigned_branches
      if (self.assigned_branches.length) {
        for (var i = 0; i < self.assigned_branches.length; i++) {
          self.data.append("assigned_to[" + i + "]", self.assigned_branches[i]);
        }
      }else{
        self.data.append("assigned_to", []);
      }
      self.data.append("_method", "put");

      axios
        .post("users/" + this.user.id, self.data)
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Update.TitleUser"),
            this.$t("Success")
          );
          Fire.$emit("Event_User");
          self.SubmitProcessing = false;
        })
        .catch(error => {
          if (error.errors.email.length > 0) {
            self.email_exist = error.errors.email[0];
          }
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          self.SubmitProcessing = false;
        });
    },

    //----------------------------- Reset Form ---------------------------\\
    reset_Form() {
      this.user = {
        id: "",
        firstname: "",
        lastname: "",
        username: "",
        password: "",
        NewPassword: null,
        email: "",
        phone: "",
        statut: "",
        role_id: "",
        avatar: "",
        is_all_branches:1,
      };
      this.data= new FormData();
      this.assigned_branches = [];
      this.email_exist= "";
    },

    //--------------------------------- Remove User ---------------------------\\
    Remove_User(id) {
      this.$swal({
        title: this.$t("Delete.Title"),
        text: this.$t("Delete.Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete.cancelButtonText"),
        confirmButtonText: this.$t("Delete.confirmButtonText")
      }).then(result => {
        if (result.value) {
          axios
            .delete("users/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.UserDeleted"),
                "success"
              );

              Fire.$emit("Delete_User");
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete.Failed"),
                "this User already linked with other operation",
                "warning"
              );
            });
        }
      });
    }
  }, // END METHODS

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Users(1);

    Fire.$on("Event_User", () => {
      setTimeout(() => {
        this.Get_Users(this.serverParams.page);
        this.$bvModal.hide("New_User");
      }, 500);
    });

    Fire.$on("Delete_User", () => {
      setTimeout(() => {
        this.Get_Users(this.serverParams.page);
      }, 500);
    });
  }
};
</script>
